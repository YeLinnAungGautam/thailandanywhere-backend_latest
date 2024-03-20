<?php
namespace App\Services\Repository;

use App\Models\BookingItem;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CarBookingRepositoryService
{
    public static function updateBooking(BookingItem $booking_item, $request)
    {
        DB::beginTransaction();

        try {
            $booking_item_data = [
                'extra_collect_amount' => $request->extra_collect_amount,
                'cost_price' => $request->cost_price,
                'total_cost_price' => $request->total_cost_price,
            ];

            $reservation_info_data = [
                'route_plan' => $request->route_plan,
                'special_request' => $request->special_request,
            ];

            $reservation_car_info_data = [
                'supplier_id' => $request->supplier_id,
                'driver_id' => $request->driver_id,
                'driver_contact' => $request->driver_contact,
                'car_number' => $request->car_number,
            ];

            $booking_item->update($booking_item_data);
            $booking_item->reservationInfo()->update($reservation_info_data);
            $booking_item->reservationCarInfo()->update($reservation_car_info_data);

            DB::commit();

            return self::getCarBooking($booking_item);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw new Exception($e->getMessage());
        }
    }

    public static function getCarBooking(BookingItem $booking_item)
    {
        return [
            'id' => $booking_item->id,
            'extra_collect' => $booking_item->extra_collect_amount ?? 0,
            'quantity' => $booking_item->quantity,
            'cost_price' => $booking_item->cost_price,
            'total_cost_price' => $booking_item->total_cost_price,

            'supplier_id' => $booking_item->reservationCarInfo->supplier_id ?? null,
            'supplier_name' => $booking_item->reservationCarInfo->supplier->name ?? null,
            'driver_id' => $booking_item->reservationCarInfo->driver_id ?? null,
            'driver_name' => $booking_item->reservationCarInfo->driver->name ?? null,
            'driver_contact' => $booking_item->reservationCarInfo->driver->contact ?? null,
            'car_number' => $booking_item->reservationCarInfo->car_number ?? null,

            'route_plan' => $booking_item->reservationInfo->route_plan ?? null,
            'special_request' => $booking_item->reservationInfo->special_request ?? null,
        ];
    }
}
