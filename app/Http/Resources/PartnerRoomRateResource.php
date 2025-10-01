<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerRoomRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available_rooms = $this->stock - ($this->booked_count ?? 0);

        $room_price = $this->room ? $this->room->room_price : 0;
        if ($this->discount_type === 'percentage') {
            $current_price = (float) ($room_price - ($room_price * $this->discount / 100));
        } else {
            $current_price = (float) ($room_price - $this->discount);
        }

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'room_name' => $this->room ? $this->room->name : null,
            'date' => $this->date,
            'display_date' => Carbon::parse($this->date)->format('D, M j Y'),

            'room_price' => (float) $room_price,
            'current_price' => $current_price,

            'discount_type' => $this->discount_type,
            'discount' => (float) $this->discount,

            'stock' => $this->stock,
            'booked_count' => $this->booked_count ?? 0,
            'available_rooms' => $available_rooms,
        ];
    }
}
