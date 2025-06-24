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
            }
        });
    }
}
