<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateExpenseReceipts implements ShouldQueue
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
        $this->migrateExpenseReceipts();
    }

    private function migrateExpenseReceipts()
    {
        DB::table('reservation_expense_receipts')->orderBy('id')->chunk(100, function ($receipts) {
            foreach ($receipts as $receipt) {
                $bookingItem = BookingItem::find($receipt->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'amount' => $receipt?->amount,
                    'bank_name' => $receipt?->bank_name,
                    'date' => $receipt?->date,
                    'is_corporate' => $receipt?->is_corporate,
                    'comment' => $receipt?->comment,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'expense_receipt',
                    'file' => $receipt->file,
                ], [
                    'file_name' => $receipt->file,
                    'meta' => json_encode($meta),
                ]);
            }
        });
    }
}
