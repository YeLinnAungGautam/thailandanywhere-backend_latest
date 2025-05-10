<?php

namespace App\Http\Resources\Cart;

use App\Http\Resources\HotelResource;
use App\Http\Resources\RoomImageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomCartResource extends JsonResource
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
            'hotel' => new HotelResource($this->hotel),
            'extra_price' => $this->extra_price,
            'room_price' => $this->room_price,
            'is_extra' => $this->is_extra,
            'has_breakfast' => $this->has_breakfast,
            'agent_price' => $this->agent_price ?? 0,
            'owner_price' => $this->owner_price,
            'description' => $this->description,
            'images' => RoomImageResource::collection($this->images),
            'max_person' => $this->max_person,
        ];
    }
}
