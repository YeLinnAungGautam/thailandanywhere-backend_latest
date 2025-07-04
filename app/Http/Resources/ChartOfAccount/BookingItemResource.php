<?php

namespace App\Http\Resources\ChartOfAccount;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->product_type == 'App\Models\Hotel') {
            $variation_name = $this->room->name;
        } elseif($this->product_type == 'App\Models\EntranceTicket') {
            $variation_name = $this->variation->name;
        } elseif($this->product_type == 'App\Models\PrivateVanTour') {
            $variation_name = $this->car->name;
        }


        return [
          'id' => $this->id,
          'booking_id' => $this->booking->id,
          'crm_id' => $this->crm_id,
          'product_name' => $this->product->name ?? '-',
          'product_type' => $this->product_type,
          'variation_name' => $variation_name,
          'service_date' => $this->service_date ? $this->service_date->format('Y-m-d') : null,
          'sale_date' => $this->booking->booking_date ,
          'balance_due_date' => $this->booking->balance_due_date ? $this->booking->balance_due_date->format('Y-m-d') : null,
          'amount' => $this->amount,
          'payment_status' => $this->booking->payment_status,
          'expense_status' => $this->payment_status,
          'payment_verify_status' => $this->booking->verify_status,
          'income' => $this->amount,
          'expense' => $this->total_cost_price,
        ];
    }
}
