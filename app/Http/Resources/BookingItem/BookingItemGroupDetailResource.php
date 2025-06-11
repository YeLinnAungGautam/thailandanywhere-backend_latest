<?php

namespace App\Http\Resources\BookingItem;

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

            'booking' => $this->whenLoaded('booking'),

            'items' => $this->whenLoaded('bookingItems'),
        ];
    }
}
