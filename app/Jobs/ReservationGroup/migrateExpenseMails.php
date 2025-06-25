<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateExpenseMails implements ShouldQueue
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
        $this->migrateExpenseMails();
    }

    private function migrateExpenseMails()
    {
        DB::table('reservation_expense_mails')->orderBy('id')->chunk(100, function ($mails) {

            $processedGroupIds = []; // Track processed group IDs

            foreach ($mails as $mail) {
                $bookingItem = BookingItem::find($mail->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'expense_mail_proof',
                    'file' => $mail->file,
                ], [
                    'file_name' => $mail->file,
                    'meta' => null,
                ]);

                // Collect unique group IDs for batch update
                if (!in_array($bookingItem->group_id, $processedGroupIds)) {
                    $processedGroupIds[] = $bookingItem->group_id;
                }
            }

            // Update sent_expense_mail = 1 for all processed group IDs in this chunk
            if (!empty($processedGroupIds)) {
                DB::table('booking_item_groups')
                    ->whereIn('id', $processedGroupIds)
                    ->update(['sent_expense_mail' => 1]);
            }
        });
    }
}
