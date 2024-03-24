<?php

namespace App\Jobs;

use App\Models\BookingItem;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MakeExpenseStatusFullyPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $crm_ids)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            foreach($this->crm_ids as $crm_id) {
                $booking_item = BookingItem::where('crm_id', $crm_id)->first();

                $booking_item->update(['payment_status' => 'fully_paid']);
            }
        } catch (Exception $e) {
            Log::error($e);
        }
    }
}
