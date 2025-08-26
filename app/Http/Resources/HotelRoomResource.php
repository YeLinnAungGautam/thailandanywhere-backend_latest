<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelRoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discount_price = ($this->room_price - $this->cost) * 0.75;
        $discount_percent = ($this->owner_price - $discount_price) / $this->owner_price * 100;
        $selling_price = $this->room_price - $discount_price;

        $data = parent::toArray($request);
        $data['images'] = RoomImageResource::collection($this->images);
        $data['discount_price'] = $discount_price;
        $data['discount_percent'] = round($discount_percent);
        $data['selling_price'] = $selling_price;

        return $data;
    }
}
