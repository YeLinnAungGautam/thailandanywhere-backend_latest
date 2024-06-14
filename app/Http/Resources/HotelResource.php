<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class HotelResource extends JsonResource
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
            'type' => $this->type,
            'legal_name' => $this->legal_name,
            'account_name' => $this->account_name,
            'contract_due' => $this->contract_due,
            'payment_method' => $this->payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'city' => new CityResource($this->city),
            'place' => $this->place,
            'rooms' => HotelRoomResource::collection($this->rooms),
            'contacts' => HotelContractResource::collection($this->contracts),
            'images' => HotelImageResource::collection($this->images),
            'facilities' => FacilityResource::collection($this->facilities),
            'lowest_room_price' => $this->rooms->where('is_extra', 0)->sortBy('room_price')->first()->room_price ?? 0,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,

            'location_map_title' => $this->location_map_title,
            'location_map' => $this->location_map,
            'rating' => $this->rating,
            'nearby_places' => isset($this->nearby_places) ? $this->getNearbyPlaces() : null
        ];
    }

    public function getNearbyPlaces()
    {
        $places = [];
        $hotel_nearby_places = json_decode($this->nearby_places);

        foreach($hotel_nearby_places as $nearby) {
            $places[] = [
                'name' => $nearby->name ?? '-',
                'distance' => $nearby->distance ?? '-',
                'image' => $nearby->image ? config('app.url') . Storage::url('images/' . $nearby->image) : null,
            ];
        }

        return $places;
    }
}
