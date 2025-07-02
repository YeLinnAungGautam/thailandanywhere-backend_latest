<?php

namespace App\Http\Resources;

use App\Http\Resources\Accountance\CashImageResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

            'sub_total' => $this->sub_total + $this->exclude_amount ?? 0,
            'grand_total' => $this->grand_total + $this->exclude_amount ?? 0,
            'exclude_amount' => $this->exclude_amount,

            'deposit' => $this->deposit,
            'discount' => $this->discount,
            'comment' => $this->comment,
            'reservation_status' => $this->reservation_status,
            'payment_notes' => $this->payment_notes,
            'balance_due' => $this->balance_due,
            'balance_due_date' => $this->balance_due_date->format('Y-m-d'),
            'created_by' => $this->createdBy,
            'bill_to' => $this->customer ? $this->customer->name : "-",
            'receipts_orignal' => isset($this->receipts) ? BookingReceiptResource::collection($this->receipts) : '',
            'receipts' => isset($this->cashImages) ? CashImageResource::collection($this->cashImages) : '',
            'items' => isset($this->items) ? BookingItemResource::collection($this->items) : '',

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

            // timestamps
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
