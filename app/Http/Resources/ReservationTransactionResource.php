<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);

        $payment_slips = $this->reservationPaymentSlips->map(fn ($payment_slip) => asset('storage/images/payment_slips/' . $payment_slip->file));

        return [
            'id' => $this->id,
            'datetime' => Carbon::parse($this->datetime)->format('Y-m-d H:i:s'),
            'supplier_id' => $this->vendorable_id,
            'supplier_name' => $this->vendorable->name,
            'total_paid' => $this->total_paid,
            'crm_ids' => $this->bookingItems->pluck('crm_id'),
            'reservation_ids' => $this->bookingItems->pluck('id'),
            'payment_slips' => $payment_slips,
            'notes' => $this->notes ?? null
        ];
    }
}
