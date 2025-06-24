<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateSupplierInfos implements ShouldQueue
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
        $this->migrateSupplierInfos();
    }

    private function migrateSupplierInfos()
    {
        DB::table('reservation_supplier_infos')->orderBy('id')->chunk(100, function ($infos) {
            foreach ($infos as $info) {
                $bookingItem = BookingItem::find($info->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'ref_number' => $info->ref_number ?? null,
                    'supplier_name' => $info->supplier_name ?? null,
                    'booking_confirm_letter' => $info->booking_confirm_letter ?? null,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'supplier_info',
                ], [
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
