<?php

namespace Database\Seeders;

use App\Models\ReservationInfo;
use Illuminate\Database\Seeder;

class MigrateLocationFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reservation_infos = ReservationInfo::query()
            ->with('bookingItem')
            ->whereNotNull('route_plan')
            ->orWhere('special_request', '<>', null)
            ->orWhere('pickup_location', '<>', null)
            ->orWhere('pickup_time', '<>', null)
            ->get();

        $this->cleanNullStringValues($reservation_infos);

        $this->migrateData($reservation_infos);
    }

    private function migrateData($reservation_infos)
    {
        foreach($reservation_infos as $rev_info) {
            $booking_item = $rev_info->bookingItem;

            if($booking_item) {
                if(!is_null($rev_info->route_plan)) {
                    $booking_item->route_plan = $rev_info->route_plan;
                }

                if(!is_null($rev_info->special_request)) {
                    $booking_item->special_request = $rev_info->special_request;
                }

                if(!is_null($rev_info->pickup_location)) {
                    $booking_item->pickup_location = $rev_info->pickup_location;
                }

                if(!is_null($rev_info->pickup_time)) {
                    $booking_item->pickup_time = $rev_info->pickup_time;
                }

                $booking_item->timestamps = false;
                $booking_item->save();
            }
        }
    }

    private function cleanNullStringValues($reservation_infos)
    {
        foreach($reservation_infos as $info) {
            if($info->route_plan === 'null') {
                $info->route_plan = null;
            }

            if($info->special_request === 'null') {
                $info->special_request = null;
            }

            if($info->pickup_location === 'null') {
                $info->pickup_location = null;
            }

            if($info->pickup_time === 'null') {
                $info->pickup_time = null;
            }

            $info->timestamps = false;
            $info->save();
        }
    }
}
