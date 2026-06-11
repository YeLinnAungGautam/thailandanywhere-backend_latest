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

        $basic_completed = !is_null($this->is_driver_collect)
            && !empty($this->car_customer_contact)
            && !empty($this->route_plan)
            && !empty($this->pickup_time)
            && !empty($this->dropoff_location)
            && !empty($this->pickup_location);

        $has_supplier = !is_null($this->reservationCarInfo->supplier_id ?? null);
        $has_cost_price = !is_null($this->cost_price);

        if ($basic_completed && $has_supplier && $has_cost_price) {
            $data_completed = 'complete';
        } elseif (!$basic_completed) {
            $data_completed = 'fill_data_not_completed';
        } else {
            $data_completed = 'not_fill_supplier';
        }

        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'service_date' => $this->service_date,
            'product_name' => $this->product->name ?? '-',
            'variation_name' => $this->acsr_variation_name,
            'reservation_status' => $this->reservation_status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->booking->payment_method,
            'selling_price' => $this->selling_price,
            'amount' => $this->amount,
            'is_driver_collect' => $this->is_driver_collect,
            'contact_number' => $this->contact_number,
            'total_pax' => $this->total_pax,
            'collect_comment' => $this->collect_comment,
            'extra_collect_amount' => $extra_collect_amount,
            'total_cost' => $total_cost,
            'balance_amount' => $balance_amount,
            'customer_name' => $this->booking->customer->name,
            'customer_payment' => $this->booking->payment_status,
            'driver_info_id' => $this->reservationCarInfo->driver_info_id ?? null,
            'car_number' => $this->reservationCarInfo->driverInfo->car_number ?? null,
            'qty'=>$this->quantity,
            'supplier_name' => $this->reservationCarInfo->supplier->name ?? '-',
            'route_plan' => $this->route_plan,
            'special_request' => $this->special_request,
            'dropoff_location' => $this->dropoff_location,
            'pickup_location' => $this->pickup_location,
            'pickup_time' => $this->pickup_time,
            'line_history' => $this->line_history,
            'booking_id' => $this->booking_id,
            'group_id' => $this->group_id,
            'is_checked' => $this->is_checked,
            'car_comment' => $this->car_comment,
            'cost_price' => $this->cost_price,
            'car_total_collect' => $this->car_total_collect,
            'data_completed' => $data_completed,
        ];
    }
}
