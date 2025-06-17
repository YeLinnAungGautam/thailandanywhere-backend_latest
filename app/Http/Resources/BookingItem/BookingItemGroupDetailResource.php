<?php

namespace App\Http\Resources\BookingItem;

use App\Http\Resources\BookingItemResource;
use App\Http\Resources\BookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemGroupDetailResource extends JsonResource
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
            'product_type' => class_basename($this->product_type),
            'total_cost_price' => $this->total_cost_price,
            'reservation_count' => $this->bookingItems->count(),
            'booking_crm_id' => $this->booking->crm_id ?? null,
            'product_name' => $this->bookingItems->first()->product->name ?? 'N/A',
            'customer_name' => $this->booking->customer->name ?? 'N/A',
            'sent_booking_request' => $this->sent_booking_request,
            'sent_expense_mail' => $this->sent_expense_mail,
            'expense_method' => $this->expense_method,
            'expense_bank_name' => $this->expense_bank_name,
            'expense_bank_account' => $this->expense_bank_account,
            'expense_status' => $this->expense_status,
            'expense_total_amount' => $this->expense_total_amount,
            'booking' => BookingResource::make($this->booking),
            'items' => BookingItemResource::collection($this->bookingItems),
        ];
    }
}
