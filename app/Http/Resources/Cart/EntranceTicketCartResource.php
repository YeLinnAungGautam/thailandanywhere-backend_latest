<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\ProductImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntranceTicketCartResource extends JsonResource
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
            'name' => $this->name,
            'price_name' => $this->price_name,
            'price' => $this->price,
            'owner_price' => $this->owner_price,
            'images' => ProductImageResource::collection($this->images),
            'meta_data' => $this->meta_data ? json_decode($this->meta_data) : null,
            'child_info' => $this->child_info ? json_decode($this->child_info) : null,
        ];
    }
}
