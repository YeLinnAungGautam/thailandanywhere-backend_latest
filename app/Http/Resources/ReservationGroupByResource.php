<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationGroupByResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'booking_date' => $this->booking_date,
            'balance_due_date' => $this->balance_due_date,
            'discount' => $this->discount,
            'sub_total' => $this->sub_total,
            'grand_total' => $this->grand_total,
            'deposit' => $this->deposit,
            'balance_due' => $this->balance_due,
            'is_inclusive' => $this->is_inclusive,
            'inclusive_name' => $this->inclusive_name,
            'inclusive_quantity' => $this->inclusive_quantity,
            'inclusive_description' => $this->inclusive_description,
            'inclusive_rate' => $this->inclusive_rate,
            'inclusive_start_date' => $this->inclusive_start_date,
            'inclusive_end_date' => $this->inclusive_end_date,
            'customer_info' => $this->customer,
            'items' => BookingItemResource::collection($this->items),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'receipts' => BookingReceiptResource::collection($this->receipts),
            'payment_currency' => $this->payment_currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'bank_name' => $this->bank_name,
        ];
    }
}
