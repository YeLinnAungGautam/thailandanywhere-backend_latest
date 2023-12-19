<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
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
            'extra_price' => $this->extra_price,
            'meal_price' => $this->meal_price,
            'is_extra' => $this->is_extra,
            'cost' => $this->cost,
            'description' => $this->description,
            'max_person' => $this->max_person,
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'updated_at' => $this->updated_at,
            'restaurant' => new RestaurantResource($this->restaurant),
            'images' => ProductImageResource::collection($this->images),
        ];
    }
}
