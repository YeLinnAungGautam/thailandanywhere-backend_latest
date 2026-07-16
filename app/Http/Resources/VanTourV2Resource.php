<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VanTourV2Resource extends JsonResource
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
            'sku_code' => $this->sku_code,
            'type' => $this->type,
            'supplier_cost' => $this->supplier_cost,

            'cities' => $this->whenLoaded('cities', function () {
                return $this->cities->map(fn ($city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                ]);
            }),

            // 'route_plans' => $this->whenLoaded('routePlans', function () {
            //     return $this->routePlans->map(fn ($plan) => [
            //         'id' => $plan->id,
            //         'name' => $plan->name ?? $plan->title ?? null,
            //     ]);
            // }),

            'route_plans' => RoutePlanResource::collection($this->whenLoaded('routePlans')),

            'cars' => $this->whenLoaded('cars', function () {
                return $this->cars->map(fn ($car) => [
                    'car_id' => $car->id,
                    'name' => $car->name,
                    'price' => (float) $car->pivot->price,
                    'agent_price' => (float) $car->pivot->agent_price,
                    'cost' => (float) $car->pivot->cost,
                ]);
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
