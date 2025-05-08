<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InclusiveDetailListResource extends JsonResource
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
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            // 'summary' => $this->summary,
            // 'summary_mm' => $this->summary_mm,
            // 'meals' => $this->meals,
            // 'cities' => CityResource::collection($this->cities),
            // 'destinations' => DestinationResource::collection($this->destinations),
            // 'restaurants' => RestaurantFResource::collection($this->restaurants),
            // 'created_at' => $this->created_at,
            // 'updated_at' => $this->updated_at,
        ];
    }
}
