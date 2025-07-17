<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashImageBookingResource extends JsonResource
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
            'cash_image_id' => $this->cash_image_id,
            'booking_id' => $this->booking_id,
            'deposit' => $this->deposit,
            'notes' => $this->notes
        ];
    }
}
