<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoUsageResource extends JsonResource
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

            'promo_id' => $this->promo_id,
            'promo' => $this->whenLoaded('promo', function () {
                return [
                    'promo_id'   => $this->promo->promo_id,
                    'promo_name' => $this->promo->promo_name,
                    'promo_code' => $this->promo->promo_code,
                    'promo_type' => $this->promo->promo_type,
                ];
            }),

            'booking_item_id' => $this->booking_item_id,
            'booking_item' => $this->whenLoaded('bookingItem', function () {
                return [
                    'id'           => $this->bookingItem->id,
                    'product_type' => $this->bookingItem->product_type,
                    'product_id'   => $this->bookingItem->product_id,
                    'amount'       => (float) $this->bookingItem->amount,
                    'booking_id'   => $this->bookingItem->booking_id,
                ];
            }),

            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id'    => $this->customer->id,
                    'name'  => $this->customer->name ?? null,
                    'email' => $this->customer->email ?? null,
                ];
            }),

            'discount_applied' => (float) $this->discount_applied,

            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d-m-Y H:i:s'),
        ];
    }
}
