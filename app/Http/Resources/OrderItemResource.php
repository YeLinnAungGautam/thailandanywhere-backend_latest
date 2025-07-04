<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = null;
        switch ($this->product_type) {
            case 'App\Models\PrivateVanTour':
                $product = new PrivateVanTourResource($this->product);

                break;
            case 'App\Models\EntranceTicket':
                $product = new EntranceTicketResource($this->product);

                break;
            case 'App\Models\Hotel':
                $product = new HotelResource($this->product);

                break;
            default:
                $product = null;

                break;
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_type' => $this->product_type,
            'variation_id' => $this->variation_id,
            'car_id' => $this->car_id,
            'room_id' => $this->room_id,
            'service_date' => $this->service_date,
            'checkin_date' => $this->checkin_date,
            'checkout_date' => $this->checkout_date,
            'quantity' => $this->quantity,
            'selling_price' => $this->selling_price,
            'total_selling_price' => $this->total_selling_price,
            'cost_price' => $this->cost_price,
            'total_cost_price' => $this->total_cost_price,
            'discount' => $this->discount,
            'special_request' => $this->special_request,
            'route_price' => $this->route_price,
            'pickup_location' => $this->pickup_location,
            'pickup_time' => $this->pickup_time,
            'individual_pricing' => $this->individual_pricing,
            'car' => new CarResource($this->car),
            'room' => new RoomResource($this->room),
            'variation' => new EntranceTicketVariationResource($this->variation),
            'product' => $product,
        ];
    }
}
