<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NearByPlaceResource extends JsonResource
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
            'icon' => $this->icon,
            'category' => $this->category,
            'sub_category' => $this->sub_category,
            'distance' => $this->distance,
            'distance_value' => $this->distance_value,
            'distance_unit' => $this->distance_unit,
            'walking_time' => $this->walking_time,
            'driving_time' => $this->driving_time,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'placeable_type' => $this->placeable_type,
            'placeable_id' => $this->placeable_id,
        ];
    }
}
