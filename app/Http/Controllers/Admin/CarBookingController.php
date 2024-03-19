<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarBookingResource;
use App\Models\BookingItem;
use App\Models\PrivateVanTour;
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
        // dd(BookingItemDataService::getTotalSummary(PrivateVanTour::class));

        $booking_item_query = BookingItem::privateVanTour()
            ->with(
                'car',
                'booking',
                'product',
                'reservationCarInfo',
                'booking.customer:id,name'
            )
            ->when($request->date, fn ($query) => $query->where('created_at', $request->date));

        if($request->supplier_id) {
            $booking_item_query = $booking_item_query->whereHas('reservationCarInfo', fn ($query) => $query->where('supplier_id', $request->supplier_id));
        } else {
            $booking_item_query = $booking_item_query->whereHas('reservationCarInfo', fn ($query) => $query->whereNull('supplier_id'));
        }

        return CarBookingResource::collection($booking_item_query->paginate($request->limit ?? 10))
            ->additional([
                'result' => 1,
                'message' => 'success',
                'suppliers' => Supplier::pluck('name', 'id')->toArray(),
                'summary' => BookingItemDataService::getTotalSummary(PrivateVanTour::class)
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

    public function update(string|int $booking_item_id, Request $request)
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
}
