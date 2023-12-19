<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'total_coast' => $this->getCostPrice() * $this->getQuantity(),
            'bank_name' => $this->product->bank_name,
            'bank_account_number' => $this->product->bank_account_number,
            'account_name' => $this->product->account_name ?? '-',
            'crm_id' => $this->booking->crm_id ,
            'reservation_code' => $this->crm_id,
            'hotel_name' => $this->product->name,
            'total_rooms' => $this->quantity,
            'total_nights' => count($this->getDaysOfMonth($this->checkin_date, $this->checkout_date)),
            'sale_price' => $this->selling_price * $this->getQuantity()
        ];

        if($this->product_type == 'App\Models\Hotel') {
            $data['checkin_date'] = $this->checkin_date ? Carbon::parse($this->checkin_date)->format('d M Y') : null;
            $data['checkout_date'] = $this->checkout_date ? Carbon::parse($this->checkout_date)->format('d M Y') : null;
        }

        return $data;
    }

    private function getCostPrice()
    {
        $cost_price = null;

        if($this->cost_price == null || $this->cost_price == 0) {
            if($this->room) {
                $cost_price = $this->room->cost ?? 0;
            }

            if($this->variation) {
                $cost_price = $this->variation->cost_price ?? 0;
            }

            if($this->car || $this->product_type == "App\Models\GroupTour" || $this->product_type == "App\Models\Airline") {
                $cost_price = 0;
            }
        } else {
            $cost_price = $this->cost_price;
        }

        return $cost_price;
    }

    private function getQuantity()
    {
        if($this->product_type == 'App\Models\Hotel') {
            return count($this->getDaysOfMonth($this->checkin_date, $this->checkout_date));
        }

        return $this->quantity;
    }

    private function getDaysOfMonth($checkin_date, $checkout_date): array
    {
        $dates = Carbon::parse($checkin_date)
            ->daysUntil(Carbon::parse($checkout_date))
            ->map(fn ($date) => $date->format('Y-m-d'));

        return iterator_to_array($dates);
    }
}
