<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'crm_id' => $this['crm_id'],
            'latest_service_date' => $this['latest_service_date'],
            'total_bookings' => $this['total_bookings'],
            'total_amount' => $this['total_amount'],
            'customer_payment_status' => $this['customer_payment_status'],
            'expense_status' => $this['expense_status'],
            'bookings' => $this['bookings'],
        ];
    }
}
