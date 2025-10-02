<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'date' => $this->date,

            'selling_price' => (float) $this->selling_price,
            'discount' => (float) $this->discount,
            'price' => (float) ($this->selling_price - $this->discount),

            'stock' => $this->stock,
            'booked_count' => $this->booked_count ?? 0,
            'available_rooms' => $available_rooms,
        ];
    }
}
