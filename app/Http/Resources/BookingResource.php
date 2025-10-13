<?php

namespace App\Http\Resources;

use App\Http\Resources\Accountance\CashImageResource;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sourceOrder = Order::where('booking_id', $this->id)->first();

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

            'sub_total' => $this->sub_total,
            'grand_total' => $this->grand_total,
            'exclude_amount' => $this->exclude_amount,

            'deposit' => $this->deposit,
            'discount' => $this->discount,
            'comment' => $this->comment,
            'reservation_status' => $this->reservation_status,
            'payment_notes' => $this->payment_notes,
            'balance_due' => $this->balance_due,
            'balance_due_date' => $this->balance_due_date->format('Y-m-d'),
            'created_by' => $this->createdBy,
            'admin' => $this->createdBy,
            'bill_to' => $this->customer ? $this->customer->name : "-",
            'receipts_orignal' => isset($this->receipts) ? BookingReceiptResource::collection($this->receipts) : '',
            'receipts' => $this->formatAllReceiptImages(),
            'items' => isset($this->items) ? BookingItemResource::collection($this->items) : '',
            'item_count' => $this->items ? $this->items->count() : 0,
            'service_start_date' => $this->start_date ? Carbon::parse($this->start_date)->format('d M Y') : null,

            // Inclusive
            'is_inclusive' => $this->is_inclusive,
            'inclusive_name' => $this->inclusive_name,
            'inclusive_description' => $this->inclusive_description,
            'inclusive_quantity' => $this->inclusive_quantity,
            'inclusive_rate' => $this->inclusive_rate,
            'inclusive_start_date' => $this->inclusive_start_date,
            'inclusive_end_date' => $this->inclusive_end_date,

            // sale case
            'cases' => isset($this->saleCases) ? CaseDetailResource::collection($this->saleCases) : '',

            // verfiy status
            'verify_status' => $this->verify_status,

            // from order
            'is_from_order' => !is_null($sourceOrder),
            'source_order' => $sourceOrder ? [
                'id' => $sourceOrder->id,
                'order_number' => $sourceOrder->order_number,
                'order_status' => $sourceOrder->order_status,
            ] : null,

            // start and end dates
            'start_date' => $this->start_date ?? null,
            'end_date' => $this->end_date ?? null,

            'output_vat' => $this->output_vat,
            'commission' => $this->commission,

            // timestamps
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * Format all receipt images including both cashImages and bCashImages
     * Groups internal transfers and combines all cash images in one place
     */
private function formatAllReceiptImages()
    {
        // Collect all cash images from both relationships
        $allCashImages = collect();

        // Add cashImages (used in original receipts) - load relationship first
        if (isset($this->cashImages)) {
            $cashImagesWithRelations = $this->cashImages->load('internalTransfers');
            $allCashImages = $allCashImages->merge($cashImagesWithRelations);
        }

        // Add bCashImages (from the cash_images field) - load relationship first
        if (isset($this->bCashImages)) {
            $bCashImagesWithRelations = $this->bCashImages->load('internalTransfers');
            $allCashImages = $allCashImages->merge($bCashImagesWithRelations);
        }

        // Remove duplicates based on ID
        $allCashImages = $allCashImages->unique('id');

        if ($allCashImages->isEmpty()) {
            return '';
        }

        // Group cash images by internal transfer
        $internalTransferGroups = [];
        $regularCashImages = [];

        foreach ($allCashImages as $cashImage) {
            // Check if this is an internal transfer
            if ($cashImage->internal_transfer) {
                // Get the internal transfer
                $internalTransfer = $cashImage->internalTransfers->first();

                if ($internalTransfer) {
                    $transferId = $internalTransfer->id;

                    // Initialize the group if not exists
                    if (!isset($internalTransferGroups[$transferId])) {
                        $internalTransferGroups[$transferId] = [
                            'is_internal_transfer' => true,
                            'internal_transfer_id' => $transferId,
                            'exchange_rate' => $internalTransfer->exchange_rate,
                            'notes' => $internalTransfer->notes,
                            'from_files' => [],
                            'to_files' => [],
                        ];
                    }

                    // Format the cash image data
                    $imageData = [
                        'id' => $cashImage->id,
                        'image' => $cashImage->image ? Storage::url('images/' . $cashImage->image) : null,
                        'date' => $cashImage->date ? $cashImage->date->format('Y-m-d H:i') : null,
                        'sender' => $cashImage->sender,
                        'receiver' => $cashImage->receiver,
                        'amount' => (float) $cashImage->amount,
                        'currency' => $cashImage->currency,
                        'interact_bank' => $cashImage->interact_bank,
                        'created_at' => $cashImage->created_at->format('d-m-Y H:i:s'),
                        'updated_at' => $cashImage->updated_at->format('d-m-Y H:i:s'),
                        'relatables' => $cashImage->relatables,
                    ];

                    // Add pivot data if it exists (from bCashImages)
                    if (isset($cashImage->pivot)) {
                        $imageData['pivot'] = [
                            'type' => $cashImage->pivot->type ?? null,
                            'deposit' => $cashImage->pivot->deposit ?? null,
                            'notes' => $cashImage->pivot->notes ?? null,
                        ];
                    }

                    // Get direction from pivot
                    $direction = $internalTransfer->pivot->direction ?? null;

                    if ($direction === 'from') {
                        $internalTransferGroups[$transferId]['from_files'][] = $imageData;
                    } elseif ($direction === 'to') {
                        $internalTransferGroups[$transferId]['to_files'][] = $imageData;
                    }
                }
            } else {
                // Regular cash image
                $regularCashImages[] = $cashImage;
            }
        }

        // Convert regular cash images using CashImageResource
        $formattedRegularImages = CashImageResource::collection(collect($regularCashImages))->resolve();

        // Combine regular images and internal transfer groups
        return array_merge($formattedRegularImages, array_values($internalTransferGroups));
    }
}
