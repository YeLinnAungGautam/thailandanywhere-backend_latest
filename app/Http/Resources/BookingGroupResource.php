<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Filter items based on expense_status if provided
        $filteredItems = $this->items;

        if ($request->has('expense_status') && isset($this->items)) {
            $filteredItems = $this->items->filter(function ($item) use ($request) {
                return $item->payment_status === $request->expense_status;
            })->values();
        }

        // Filter booking based on customer_payment_status if provided
        if ($request->has('customer_payment_status') && $this->payment_status !== $request->customer_payment_status) {
            $filteredItems = collect([]);
        }

        // Sort grouped items to put true values first
        $sortedGroupedItems = null;
        if (isset($this->groupedItems)) {
            $sortedGroupedItems = $this->groupedItems->map(function($items, $productId) {
                // Sort items in each group
                return $items->sortByDesc(function($item) {
                    // Add your sorting logic here
                    // For example, sort by reservation_status == "confirmed"
                    return $item->reservation_status === "confirmed" ? 1 : 0;
                });
            });
        }

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'crm_id' => $this->crm_id,
            'is_past_info' => $this->is_past_info,
            'past_user_id' => $this->past_user_id,
            'past_user' => $this->pastUser,
            'past_crm_id' => $this->past_crm_id,
            'customer' => $this->customer,
            'user' => $this->user,
            'sold_from' => $this->sold_from,
            'payment_currency' => $this->payment_currency,
            'payment_method' => $this->payment_method,
            'bank_name' => $this->bank_name,
            'transfer_code' => $this->transfer_code,
            'payment_status' => $this->payment_status,
            'booking_date' => $this->booking_date,
            'money_exchange_rate' => $this->money_exchange_rate,

            'sub_total' => $this->sub_total + $this->exclude_amount ?? 0,
            'grand_total' => $this->grand_total + $this->exclude_amount ?? 0,
            'exclude_amount' => $this->exclude_amount,

            'deposit' => $this->deposit,
            'discount' => $this->discount,
            'comment' => $this->comment,
            'reservation_status' => $this->reservation_status,
            'payment_notes' => $this->payment_notes,
            'balance_due' => $this->balance_due,
            'balance_due_date' => $this->balance_due_date,
            'created_by' => $this->createdBy,
            'bill_to' => $this->customer ? $this->customer->name : "-",
            'receipts' => isset($this->receipts) ? BookingReceiptResource::collection($this->receipts) : '',
            'items' => isset($filteredItems) ? BookingItemResource::collection($filteredItems) : '',
            'grouped_items' => $this->when(isset($sortedGroupedItems), function() use ($sortedGroupedItems) {
                $result = [];
                foreach($sortedGroupedItems as $productId => $items) {
                    $result[] = [
                        'product_id' => $productId,
                        'items' => BookingItemResource::collection($items)
                    ];
                }

                // Sort the groups to have groups with confirmed status first
                usort($result, function($a, $b) {
                    $aHasConfirmed = collect($a['items'])->contains(function ($item) {
                        return $item['reservation_status'] === 'confirmed';
                    });

                    $bHasConfirmed = collect($b['items'])->contains(function ($item) {
                        return $item['reservation_status'] === 'confirmed';
                    });

                    return $bHasConfirmed <=> $aHasConfirmed;
                });

                return $result;
            }),

            // Inclusive
            'is_inclusive' => $this->is_inclusive,
            'inclusive_name' => $this->inclusive_name,
            'inclusive_description' => $this->inclusive_description,
            'inclusive_quantity' => $this->inclusive_quantity,
            'inclusive_rate' => $this->inclusive_rate,
            'inclusive_start_date' => $this->inclusive_start_date,
            'inclusive_end_date' => $this->inclusive_end_date,

            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
