<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migratePaidSlips implements ShouldQueue
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
        $this->migratePaidSlips();
    }

    private function migratePaidSlips()
    {
        DB::table('reservation_paid_slips')->orderBy('id')->chunk(100, function ($slips) {
            foreach ($slips as $slip) {
                $bookingItem = BookingItem::find($slip->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }
                $meta = [
                    'amount' => $slip->amount ?? null,
                    'bank_name' => $slip->bank_name ?? null,
                    'date' => $slip->date ?? null,
                    'is_corporate' => $slip->is_corporate ?? null,
                    'comment' => $slip->comment ?? null,
                ];
                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'confirmation_letter',
                    'file' => $slip->file,
                ], [
                    'file_name' => $slip->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
