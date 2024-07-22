<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarResource extends JsonResource
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
            'product_type' => $this->product_type,
            'service_date' => $this->service_date,
            'crm_id' => $this->crm_id,
            'payment_method' => $this->payment_method ?? '-',
            'payment_status' => $this->booking->payment_status ?? '-',
            'expense_status' => $this->payment_status ?? '-',
            'reservation_status' => $this->reservation_status ?? '-',
            'past_crm_id' => $this->booking->past_crm_id ?? '-',
            'product_id' => $this->product->name ?? '-',
            'booking' => $this->booking,
            'product' => $this->product ?? null,
            'car' => $this->car,
            'variation' => $this->variation,
            'room' => $this->room,
        ];
    }
}
