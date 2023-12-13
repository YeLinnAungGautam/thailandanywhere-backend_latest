<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
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
            'cost' => $this->cost,
            'description' => $this->description,
            'images' => RoomImageResource::collection($this->images),
            'room_periods' => RoomPeriodResource::collection($this->periods),
            'max_person' => $this->max_person,
            'deleted_at' => $this->deleted_at,
            'updated_at' => $this->updated_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
