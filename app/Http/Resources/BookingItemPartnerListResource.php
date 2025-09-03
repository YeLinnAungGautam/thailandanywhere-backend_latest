<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemPartnerListResource extends JsonResource
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
            case 'App\Models\Hotel':
                $product = new HotelResource($this->product);

                break;
            default:
                $product = null;

                break;
        }
        return [
            'id' => $this->id,
            'crm_id' => $this->crm_id,
            'booking_id' => $this->group->id,
            'booking_crm_id' => $this->booking->crm_id,
            'customer_name' => $this->booking->customer->name,
            'product' => $product,
            'room' => $this->room ? new RoomResource($this->room) : null,
            'quantity' => $this->quantity,
            'checkin_date' => $this->checkin_date,
            'checkout_date' => $this->checkout_date,
            'days' => $this->checkin_date ? Carbon::parse($this->checkout_date)->diffInDays(Carbon::parse($this->checkin_date)) : 'N/A',
            'total_quantity' => $this->quantity * Carbon::parse($this->checkout_date)->diffInDays(Carbon::parse($this->checkin_date)),
            'service_date' => $this->service_date,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
