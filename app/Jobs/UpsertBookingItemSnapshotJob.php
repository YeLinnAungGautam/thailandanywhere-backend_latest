<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BookingItem;
use App\Services\BookingItemSnapshotService;
use Illuminate\Support\Facades\Log;

class UpsertBookingItemSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $bookingItem;

    /**
     * Create a new job instance.
     */
    public function __construct(BookingItem $bookingItem)
    {
        $this->bookingItem = $bookingItem;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $snapshotService = new BookingItemSnapshotService();
            $snapshots = $snapshotService->buildSnapshot($this->bookingItem);

            $this->bookingItem->update([
                'product_snapshot'   => $snapshots['product_snapshot'],
                'variation_snapshot' => $snapshots['variation_snapshot'],
                'price_snapshot'     => $snapshots['price_snapshot'],
                'archive_snapshot'   => $snapshots['archive_snapshot'],
            ]);
        } catch (\Exception $e) {
            Log::error('Snapshot save error from job: ' . $e->getMessage() . ' for Booking Item ID: ' . $this->bookingItem->id);
        }
    }
}
