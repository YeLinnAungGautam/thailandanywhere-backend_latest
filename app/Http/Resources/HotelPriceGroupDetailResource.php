<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelPriceGroupDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'hotel_id'       => $this->product_id,
            'hotel_name'     => $this->hotel_name,
            'total_quantity' => (int)   $this->total_quantity,
            'total_amount'   => (float) $this->total_amount,
            'booking_count'  => (int)   $this->booking_count,
            'room_min_price' => (float) $this->room_min_price,
            'room_max_price' => (float) $this->room_max_price,
            'booking_items'  => $this->booking_items,
        ];
    }
}
