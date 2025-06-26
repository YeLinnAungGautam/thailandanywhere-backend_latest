<?php

namespace App\Jobs\ReservationGroup;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class migrateBookingRequestDocuments implements ShouldQueue
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
        $this->migrateBookingRequestDocuments();
    }

    private function migrateBookingRequestDocuments()
    {
        DB::table('reservation_booking_requests')->orderBy('id')->chunk(100, function ($requests) {
            $processedGroupIds = []; // Track processed group IDs
            foreach ($requests as $request) {
                $bookingItem = BookingItem::find($request->booking_item_id);

                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                $meta = [
                    'is_approved' => $bookingItem->is_booking_request,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'booking_request_proof',
                    'file_name' => $request->file,
                ], [
                    'file' => $request->file,
                    'meta' => json_encode($meta),
                ]);

                // Collect unique group IDs for batch update
                if (!in_array($bookingItem->group_id, $processedGroupIds)) {
                    $processedGroupIds[] = $bookingItem->group_id;
                }
            }

            // Update sent_booking_request = 1 for all processed group IDs in this chunk
            if (!empty($processedGroupIds)) {
                DB::table('booking_item_groups')
                    ->whereIn('id', $processedGroupIds)
                    ->update(['sent_booking_request' => 1]);
            }
        });
    }
}
