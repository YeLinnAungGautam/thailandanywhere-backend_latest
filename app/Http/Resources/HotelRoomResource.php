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
        $discount_price = (float)$this->room_price - (float)$this->cost;
        $discount_price = $discount_price * 0.75;
        $owner_price = (float)$this->owner_price;
        if ($owner_price != 0) {
            $discount_percent = ($owner_price - $discount_price) / $owner_price * 100;
        } else {
            $discount_percent = 0;
        }
        $selling_price = (float)$this->room_price - $discount_price;

        $data = parent::toArray($request);
        $data['images'] = RoomImageResource::collection($this->images);
        $data['discount_price'] = $discount_price;
        $data['discount_percent'] = round($discount_percent);
        $data['selling_price'] = $selling_price;

        return $data;
    }
}
