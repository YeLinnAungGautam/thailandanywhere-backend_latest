<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\ProductImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntranceTicketVariationCartResource extends JsonResource
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
            'agent_price' => $this->agent_price ?? 0,
            'owner_price' => $this->owner_price,
            'adult_info' => $this->adult_info,
            'description' => $this->description,
            'is_add_on' => $this->is_add_on,
            'add_on_price' => $this->add_on_price,
            'images' => ProductImageResource::collection($this->images),
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'including_services' => $this->including_services ? json_decode($this->including_services) : $this->including_services,
            'meta_data' => $this->meta_data ? json_decode($this->meta_data) : null,
            'child_info' => $this->child_info ? json_decode($this->child_info) : null,
        ];
    }
}
