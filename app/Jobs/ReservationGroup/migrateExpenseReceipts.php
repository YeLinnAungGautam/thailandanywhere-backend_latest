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
            $processedGroupIds = []; // Track processed group IDs to avoid duplicate updates

            foreach ($receipts as $receipt) {
                $bookingItem = BookingItem::find($receipt->booking_item_id);
                if (!$bookingItem || !$bookingItem->group_id) {
                    continue;
                }

                // need meta fill by Kaung
                $meta = [
                    'amount' => $receipt?->amount,
                    'bank_name' => $receipt?->bank_name,
                    'date' => $receipt?->date,
                    'is_corporate' => $receipt?->is_corporate,
                    'comment' => $receipt?->comment,
                    'sender' => $receipt?->sender,
                    'reciever' => $receipt?->reciever,
                    'interact_bank' => $receipt?->interact_bank,
                    'currency' => $receipt?->currency,
                ];

                DB::table('customer_documents')->updateOrInsert([
                    'booking_item_group_id' => $bookingItem->group_id,
                    'type' => 'expense_receipt',
                    'file' => $receipt->file,
                ], [
                    'file_name' => $receipt->file,
                    'meta' => json_encode($meta),
                ]);

                if (!in_array($bookingItem->group_id, $processedGroupIds)) {
                    $processedGroupIds[] = $bookingItem->group_id;
                }
            }

            if (!empty($processedGroupIds)) {
                $this->updateBookingItemGroups($processedGroupIds);
            }
        });
    }

    /**
     * Update booking_item_groups with expense information
     */
    private function updateBookingItemGroups($groupIds)
    {
        foreach ($groupIds as $groupId) {
            // Get booking items for this group with their reservation info and payment details
            $bookingItemsData = DB::table('booking_items as bi')
                ->leftJoin('reservation_infos as ri', 'bi.id', '=', 'ri.booking_item_id')
                ->select([
                    'bi.payment_status',
                    'bi.payment_method',
                    'ri.bank_name',
                    'ri.bank_account_number'
                ])
                ->where('bi.group_id', $groupId)
                ->whereNotNull('bi.payment_status')
                ->orWhereNotNull('bi.payment_method')
                ->orWhereNotNull('ri.bank_name')
                ->orWhereNotNull('ri.bank_account_number')
                ->first(); // Get first available data

            // If no relevant data found, skip
            if (!$bookingItemsData) {
                continue;
            }

            // Prepare update data for booking_item_groups
            $updateData = [];

            if (!empty($bookingItemsData->payment_method)) {
                $updateData['expense_method'] = $bookingItemsData->payment_method;
            }

            if (!empty($bookingItemsData->payment_status)) {
                $updateData['expense_status'] = $bookingItemsData->payment_status;
            }

            if (!empty($bookingItemsData->bank_name)) {
                $updateData['expense_bank_name'] = $bookingItemsData->bank_name;
            }

            if (!empty($bookingItemsData->bank_account_number)) {
                $updateData['expense_bank_account'] = $bookingItemsData->bank_account_number;
            }

            // Update booking_item_groups if there's data to update
            if (!empty($updateData)) {
                DB::table('booking_item_groups')
                    ->where('id', $groupId)
                    ->update($updateData);
            }
        }
    }
}
