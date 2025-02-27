<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReservationBookingConfirmLetterResource extends JsonResource
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
            'booking_item_id' => $this->booking_item_id,
            'amount' => $this->amount,
            'invoice' => $this->invoice->format('d-m-Y H:i:s'),
            'due_date' => $this->due_date->format('d-m-Y H:i:s'),
            'customer' => $this->customer,
            'sender_name' => $this->sender_name,
            'file' => $this->file ? Storage::url('images/' . $this->file) : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
