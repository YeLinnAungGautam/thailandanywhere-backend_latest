<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
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
            'description' => $this->description,
            'account_name' => $this->account_name,
            'contract_due' => $this->contract_due,
            'payment_method' => $this->payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'city' => new CityResource($this->city),
            'place' => $this->place,
            'meals' => RestaurantMealResource::collection($this->meals),
            'contacts' => ProductContractResource::collection($this->contracts),
            'images' => ProductImageResource::collection($this->images),
            'lowest_meal_price' => $this->meals->where('is_extra', 0)->sortBy('meal_price')->first()->meal_price ?? 0,
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
