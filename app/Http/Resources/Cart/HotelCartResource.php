<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\CityResource;
use App\Http\Resources\HotelImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelCartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'full_description' => $this->full_description,
            'full_description_en' => $this->full_description_en,
            'type' => $this->type,
            'city' => new CityResource($this->city),
            'place' => $this->place,
            'hotel_place' => $this->hotelPlace,
            'images' => HotelImageResource::collection($this->images),
            'lowest_room_price' => $this->rooms->where('is_extra', 0)->sortBy('room_price')->first()->room_price ?? 0,
            'lowest_walk_in_price' => $this->rooms->where('is_extra', 0)->whereNotNull('owner_price')->sortBy('owner_price')->first()->owner_price ?? 0,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
            'location_map_title' => $this->location_map_title,
            'location_map' => $this->location_map,
            'rating' => $this->rating,
        ];
    }
}
