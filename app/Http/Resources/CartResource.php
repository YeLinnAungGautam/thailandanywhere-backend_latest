<?php

namespace App\Http\Resources;

use App\Http\Resources\Cart\EntranceTicketCartResource;
use App\Http\Resources\Cart\HotelCartResource;
use App\Http\Resources\Cart\InclusiveCartResource;
use App\Http\Resources\Cart\PrivateVanTourCartResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
    // Product type အလိုက် resource ခွဲခြားခြင်း
    $productResource = match ($this->product_type) {
        'App\Models\PrivateVanTour' => new PrivateVanTourCartResource($this->product),
        'App\Models\EntranceTicket' => new EntranceTicketCartResource($this->product),
        'App\Models\Hotel' => new HotelCartResource($this->product),
        'App\Models\Inclusive' => new InclusiveCartResource($this->product),
        default => null,
    };

    return [
        'id' => $this->id,
        'user_id' => $this->user_id,
        'product_type' => $this->product_type,
        'product_id' => $this->product_id,
        'product' => $productResource,
        'variation_id' => $this->variation_id,
        'variation' => $this->getVariationAttribute(),
        'quantity' => $this->quantity,
        'service_date' => $this->service_date?->format('Y-m-d'),
        'checkout_date' => $this->checkout_date?->format('Y-m-d'),
        'options' => $this->options,
        'selling_price' => $this->product->selling_price ?? null,
        'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
    ];
}
}
