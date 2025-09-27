<?php

namespace App\Http\Resources\Accountance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnpaidResource extends JsonResource
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
            'crm_id' => $this->crm_id,
            'balance_due_date' => $this->balance_due_date,
            'balance_due' => $this->balance_due,
            'grand_total' => $this->grand_total,
            'deposit' => $this->deposit,
            'payment_status' => $this->payment_status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'booking_date' => $this->booking_date,
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'email' => $this->customer->email,
                    'phone' => $this->customer->phone,
                ];
            }),
            'include_vantour' => $this->whenLoaded('items', function(){
                return $this->items->where('product_type', 'App\Models\PrivateVanTour')->first() ? true : false;
            }),
            'include_flight' => $this->whenLoaded('items', function(){
                return $this->items->where('product_type', 'App\Models\Airline')->first() ? true : false;
            }),
        ];
    }


}
