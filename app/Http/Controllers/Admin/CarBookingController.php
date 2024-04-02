<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarBookingRequest;
use App\Http\Resources\CarBookingResource;
use App\Models\BookingItem;
use App\Models\Supplier;
use App\Services\BookingItemDataService;
use App\Services\Repository\CarBookingRepositoryService;
use App\Traits\HttpResponses;
use Exception;
use Illuminate\Http\Request;

class CarBookingController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $booking_item_query = BookingItem::privateVanTour()
            ->with(
                'car',
                'booking',
                'product',
                'reservationCarInfo',
                'reservationInfo:id,booking_item_id,pickup_location,pickup_time',
                'booking.customer:id,name'
            )
            ->when($request->daterange, function ($query) use ($request) {
                $dates = explode(',', $request->daterange);

                $query->where('service_date', '>=', $dates[0])->where('service_date', '<=', $dates[1]);
            })
            ->when($request->agent_id, function ($query) use ($request) {
                $query->whereHas('booking', fn ($q) => $q->where('created_by', $request->agent_id));
            });

        if($request->supplier_id) {
            $booking_item_query = $booking_item_query->whereHas('reservationCarInfo', fn ($query) => $query->where('supplier_id', $request->supplier_id));
        } else {
            $booking_item_query = $booking_item_query->whereDoesntHave('reservationCarInfo')
                ->orWhereHas('reservationCarInfo', function ($query) {
                    $query->whereNull('supplier_id');
                });
        }

        $booking_item_query->orderByDESC('created_at');

        return CarBookingResource::collection($booking_item_query->paginate($request->limit ?? 10))
            ->additional([
                'result' => 1,
                'message' => 'success',
                'suppliers' => Supplier::pluck('name', 'id')->toArray(),
                'summary' => BookingItemDataService::getCarBookingSummary($request->all())
            ]);
    }

    public function edit(string|int $booking_item_id)
    {
        $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

        if(is_null($booking_item)) {
            return $this->error(null, "Car booking not found", 404);
        }

        return $this->success(CarBookingRepositoryService::getCarBooking($booking_item), 'Edit car booking');
    }

    public function update(string|int $booking_item_id, CarBookingRequest $request)
    {
        try {
            $booking_item = BookingItem::privateVanTour()->find($booking_item_id);

            if(is_null($booking_item)) {
                throw new Exception('Car booking not found');
            }

            $data = CarBookingRepositoryService::updateBooking($booking_item, $request);

            return $this->success($data, 'Car booking updated successfully');
        } catch (Exception $e) {
            $this->error(null, $e->getMessage(), 500);
        }
    }

    public function getSummary(Request $request)
    {
        return $this->success(BookingItemDataService::getCarBookingSummary($request->all()), 'Success car booking summary');
    }
}
