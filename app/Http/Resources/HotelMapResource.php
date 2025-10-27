<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelMapResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lowest_room_price = $this->rooms->where('is_extra', 0)->sortBy('room_price')->first()->room_price ?? 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rating' => $this->rating,
            'images' => HotelImageResource::collection($this->images),
            'place' => $this->place,
            'city_id' => $this->city_id,
            'lowest_room_price' => $lowest_room_price,
        ];
    }
}
