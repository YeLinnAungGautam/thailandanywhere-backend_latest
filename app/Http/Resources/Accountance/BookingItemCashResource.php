<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemCashResource extends JsonResource
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
            'booking' => new BookingCashImageResource($this),
            'crm_id' => $this->crm_id,
            'pivot' => $this->pivot,
        ];
    }
}
