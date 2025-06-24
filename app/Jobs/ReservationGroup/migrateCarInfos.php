<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateCarInfos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->migrateCarInfos();
    }

    private function migrateCarInfos()
    {
        DB::table('reservation_car_infos')->orderBy('id')->chunk(100, function ($infos) {
            foreach ($infos as $info) {
                $bookingItem = BookingItem::find($info->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'driver_contact' => $info->driver_contact ?? null,
                    'account_holder_name' => $info->account_holder_name ?? null,
                    'supplier_id' => $info->supplier_id ?? null,
                    'driver_id' => $info->driver_id ?? null,
                    'driver_info_id' => $info->driver_info_id ?? null,
                    'supplier_name' => $info->supplier_name ?? null,
                    'driver_name' => $info->driver_name ?? null,
                    'driver_contact' => $info->driver_contact ?? null,
                    'car_number' => $info->car_number ?? null,
                    'account_holder_name' => $info->account_holder_name ?? null,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'assign_driver',
                    'file' => $info->car_photo,
                ], [
                    'file_name' => $info->car_photo,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
