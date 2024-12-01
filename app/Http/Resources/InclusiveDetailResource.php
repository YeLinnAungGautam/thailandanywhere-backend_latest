<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InclusiveDetailResource extends JsonResource
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
            'day_name' => $this->day_name,
            'title' => $this->title,
            'image' => $this->image,
            'summary' => $this->summary,
            'meals' => $this->meals,
            'cities' => CityResource::collection($this->cities),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
