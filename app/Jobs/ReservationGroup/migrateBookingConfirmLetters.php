<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateBookingConfirmLetters implements ShouldQueue
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
        $this->migrateBookingConfirmLetters();
    }

    private function migrateBookingConfirmLetters()
    {
        DB::table('reservation_booking_confirm_letters')->orderBy('id')->chunk(100, function ($letters) {
            foreach ($letters as $letter) {
                $bookingItem = BookingItem::find($letter->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'amount' => $letter->amount ?? null,
                    'invoice' => $letter->invoice ?? null,
                    'due_date' => $letter->due_date ?? null,
                    'customer' => $letter->customer ?? null,
                    'sender_name' => $letter->sender_name ?? null,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    // 'type' => 'booking_confirm_letter',
                    'type' => 'invoice',
                    'file' => $letter->file,
                ], [
                    'file_name' => $letter->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
