<?php

namespace App\Http\Resources;

use App\Services\BookingItemDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        $data_service = new BookingItemDataService($this->resource);

        $total_cost = $data_service->getTotalCost();
        $extra_collect_amount = $this->extra_collect_amount ?? 0;
        $balance_amount = $data_service->calcBalanceAmount($this->booking->payment_method, $total_cost, $this->selling_price, $extra_collect_amount);

        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'service_date' => $this->service_date,
            'product_name' => $this->product->name,
            'variation_name' => $this->acsr_variation_name,
            'reservation_status' => $this->reservation_status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->booking->payment_method,
            'selling_price' => $this->selling_price,
            'extra_collect_amount' => $extra_collect_amount,
            'total_cost' => $total_cost,
            'balance_amount' => $balance_amount,
            'customer_name' => $this->booking->customer->name,
            'driver_info_id' => $this->reservationCarInfo->driver_info_id ?? null,
            'car_number' => $this->reservationCarInfo->driverInfo->car_number ?? null,
        ];
    }
}
