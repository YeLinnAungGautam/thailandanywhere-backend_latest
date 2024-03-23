<?php
namespace App\Services\Repository;

use App\Models\BookingItem;
use App\Models\ReservationCarInfo;
use App\Models\ReservationInfo;
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

            $booking_item->update($booking_item_data);

            ReservationInfo::updateOrCreate(
                ['booking_item_id' => $booking_item->id],
                [
                    'route_plan' => $request->route_plan,
                    'special_request' => $request->special_request,
                ]
            );

            ReservationCarInfo::updateOrCreate(
                ['booking_item_id' => $booking_item->id],
                [
                    'supplier_id' => $request->supplier_id,
                    'driver_id' => $request->driver_id,
                    'driver_info_id' => $request->driver_info_id,
                    'driver_contact' => $request->driver_contact,
                    'car_number' => $request->car_number,
                ]
            );

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
            // 'car_number' => $booking_item->reservationCarInfo->car_number ?? null,
            'driver_info_id' => $booking_item->reservationCarInfo->driver_info_id ?? null,
            'car_number' => $booking_item->reservationCarInfo->driverInfo->car_number ?? null,

            'route_plan' => $booking_item->reservationInfo->route_plan ?? null,
            'special_request' => $booking_item->reservationInfo->special_request ?? null,
        ];
    }
}
