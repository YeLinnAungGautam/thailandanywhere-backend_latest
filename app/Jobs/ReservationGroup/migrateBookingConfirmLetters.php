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
                    'product_type' => $letter->product_type ?? null,
                    'product_id' => $letter->product_id ?? null,
                    'company_legal_name' => $letter->company_legal_name ?? null,
                    'receipt_date' => $letter->receipt_date ?? null,
                    'service_start_date' => $letter->service_start_date ?? null,
                    'service_end_date' => $letter->service_end_date ?? null,
                    'receipt_image' => $letter->receipt_image ?? null,
                    'total_tax_withold' => $letter->total_tax_withold ?? null,
                    'total_before_tax' => $letter->total_before_tax ?? null,
                    'total_after_tax' => $letter->total_after_tax ?? null,
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
