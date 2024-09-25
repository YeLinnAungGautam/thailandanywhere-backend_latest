<?php
namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarBookingResource;
use App\Models\BookingItem;
use Illuminate\Http\Request;

class CarBookingController extends Controller
{
    public function index(Request $request)
    {
        $supplier = $request->user();

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
            })
            ->whereIn('id', function ($query) use ($supplier) {
                $query->select('booking_item_id')->from('reservation_car_infos')->where('supplier_id', $supplier->id);
            });

        $booking_item_query->orderByDESC('created_at');

        return CarBookingResource::collection($booking_item_query->paginate($request->limit ?? 10))
            ->additional([
                'result' => 1,
                'message' => 'success',
            ]);
    }
}
