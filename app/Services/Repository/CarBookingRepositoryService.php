<?php
namespace App\Services\Repository;

use App\Models\BookingItem;
use App\Models\Driver;
use App\Models\ReservationCarInfo;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CarBookingRepositoryService
{
    public static function assignToDriver(BookingItem $bookingItem, Driver $driver)
    {
        DB::beginTransaction();

        try {
            $bookingItem->reservationCarInfo()->updateOrCreate(
                ['booking_item_id' => $bookingItem->id],
                [
                    'driver_id' => $driver->id,
                    'driver_info_id' => $driver->driverInfos->first()->id,
                    'driver_contact' => $driver->contact,
                    'car_number' => $driver->driverInfos->first()->car_number,
                ]
            );

            DB::commit();

            return self::getCarBooking($bookingItem);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);

            throw new Exception($e->getMessage());
        }
    }

    public static function updateBooking(BookingItem $booking_item, $request)
    {
        DB::beginTransaction();

        try {
            $booking_item_data = [
                'cost_price' => $request->cost_price ?? null,
                'total_cost_price' => $request->total_cost_price ?? 0,
                'dropoff_location' => $request->dropoff_location,
                'route_plan' => $request->route_plan,
                'special_request' => $request->special_request,
                'pickup_location' => $request->pickup_location,
                'pickup_time' => $request->pickup_time,
                'is_driver_collect' => $request->has('is_driver_collect') ? (bool)$request->is_driver_collect : false,
            ];

            if ($request->has('is_driver_collect') && (bool)$request->is_driver_collect) {
                $booking_item_data['extra_collect_amount'] = $request->extra_collect_amount;
            } else {
                // Set extra_collect_amount to null when is_driver_collect is false
                $booking_item_data['extra_collect_amount'] = null;
            }

            $booking_item->update($booking_item_data);

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
            'is_driver_collect' => $booking_item->is_driver_collect,
            'extra_collect' => $booking_item->extra_collect_amount ?? 0,
            'quantity' => $booking_item->quantity,
            'cost_price' => $booking_item->cost_price,
            'total_cost_price' => $booking_item->total_cost_price,

            'route_plan' => $booking_item->route_plan,
            'special_request' => $booking_item->special_request,
            'dropoff_location' => $booking_item->dropoff_location,
            'pickup_location' => $booking_item->pickup_location,
            'pickup_time' => $booking_item->pickup_time,

            'supplier_id' => $booking_item->reservationCarInfo->supplier_id ?? null,
            'supplier_name' => $booking_item->reservationCarInfo->supplier->name ?? null,
            'driver_id' => $booking_item->reservationCarInfo->driver_id ?? null,
            'driver_name' => $booking_item->reservationCarInfo->driver->name ?? null,
            'driver_contact' => $booking_item->reservationCarInfo->driver->contact ?? null,
            'driver_info_id' => $booking_item->reservationCarInfo->driver_info_id ?? null,
            'car_number' => $booking_item->reservationCarInfo->driverInfo->car_number ?? null,
        ];
    }
}
