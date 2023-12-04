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
            'legal_name' => $this->legal_name,
            'account_name' => $this->account_name,
            'contract_due' => $this->contract_due,
            'payment_method' => $this->payment_method,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'city' => new CityResource($this->city),
            'place' => $this->place,
            'rooms' => $this->rooms,
            'contacts' => HotelContractResource::collection($this->contracts),
            'images' => HotelImageResource::collection($this->images),
            'lowest_room_price' => $this->rooms->sortBy('room_price')->first()->room_price ?? 0,
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
