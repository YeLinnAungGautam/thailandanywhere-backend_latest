<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivateVanTourCarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discount = vantour_discount();
        $discount_price = $this->pivot->cost == 0 ? 0 : ((float) $this->pivot->price - (float) $this->pivot->cost) * $discount;
        $selling_price = (float) $this->pivot->price - (float) $discount_price;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'max_person' => $this->max_person,
            'price' => $this->pivot->price,
            'selling_price' => $selling_price,
            'discount' => $discount,
            'discount_price' => $discount_price,
            'cost' => $this->pivot->cost,
            'agent_price' => $this->pivot->agent_price,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
