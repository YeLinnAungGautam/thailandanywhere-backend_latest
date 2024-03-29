<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        ];
    }
}
