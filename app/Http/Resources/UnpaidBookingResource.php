<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnpaidBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'agent_name' => $this->createdBy->name,
            'total_booking' => $this->total_booking,
            'total_balance' => $this->total_balance,
            'booking_infos' => $this->getBookingInfos()
        ];
    }

    public function getBookingInfos()
    {
        $booking_ids = explode(',', $this->booking_ids);

        $infos = [];
        foreach($booking_ids as $booking_id) {
            $booking = Booking::find($booking_id);

            $infos[] = [
                'customer_name' => $booking->customer->name,
                'crm_id' => $booking->crm_id,
                'balance_due' => $booking->balance_due
            ];
        }

        return $infos;
    }
}
