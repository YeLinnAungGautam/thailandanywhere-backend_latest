<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderPaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_slip' => $this->payment_slip ? Storage::url('order_payments/' . $this->payment_slip) : null,
            'payment_date' => $this->payment_date,
            'status' => $this->status,
            'approved_by' => $this->approver,
            'created_at' => $this->created_at,
        ];
    }
}
