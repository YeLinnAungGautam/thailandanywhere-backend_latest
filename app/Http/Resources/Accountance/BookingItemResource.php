<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\AirlineResource;
use App\Http\Resources\AirportPickupResource;
use App\Http\Resources\EntranceTicketResource;
use App\Http\Resources\GroupTourResource;
use App\Http\Resources\HotelResource;
use App\Http\Resources\InclusiveProductResource;
use App\Http\Resources\PrivateVanTourResource;
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
            $product = null;
            switch ($this->product_type) {
                case 'App\Models\PrivateVanTour':
                    $product = new PrivateVanTourResource($this->product);

                    break;
                case 'App\Models\GroupTour':
                    $product = new GroupTourResource($this->product);

                    break;
                case 'App\Models\EntranceTicket':
                    $product = new EntranceTicketResource($this->product);

                    break;
                case 'App\Models\AirportPickup':
                    $product = new AirportPickupResource($this->product);

                    break;
                case 'App\Models\Hotel':
                    $product = new HotelResource($this->product);

                    break;
                case 'App\Models\Airline':
                    $product = new AirlineResource($this->product);

                    break;

                case 'App\Models\InclusiveProduct':
                    $product = new InclusiveProductResource($this->product);

                    break;
                default:
                    $product = null;

                    break;
            }

            return [
                'id' => $this->id,
                'crm_id' => $this->crm_id,
                'product_type' => $this->product_type,
                'product_id' => $this->product_id,
                'product' => $product,
                'car' => $this->car,
                'room' => $this->room,
                'ticket' => $this->ticket,
                'variation' => $this->variation,
                'service_date' => $this->service_date ? $this->service_date->format('Y-m-d') : null,
                'quantity' => $this->quantity,
                'selling_price' => $this->selling_price,
                'cost_price' => $this->cost_price,
                'payment_method' => $this->payment_method,
                'payment_status' => $this->payment_status,
                'booking_status' => $this->booking_status,
                'bank_name' => $this->bank_name,
                'cost' => $this->cost,
                'group_id' => $this->group_id,
                'amount' => $this->amount,
                'total_cost_price' => $this->total_cost_price,
                'commission' => $this->commission,
                'output_vat' => $this->output_vat,
                'discount' => $this->discount,
                'is_inclusive' => $this->is_inclusive,

                'selling_price' => $this->selling_price,
                // 'paid_slip' => $this->paid_slip ? Storage::url('images/' . $this->paid_slip) : null,
                'created_at' => $this->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
                'individual_pricing' => $this->individual_pricing ? (is_string($this->individual_pricing) ? json_decode($this->individual_pricing) : $this->individual_pricing) : null,
            ];
        }
    }

