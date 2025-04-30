<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemAmendmentResource extends JsonResource
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
            'booking_item' => new BookingItemResource($this->whenLoaded('bookingItem')),
            'amend_history' => $this->amend_history,
            'amend_request' => $this->amend_request,
            'amend_mail_sent' => $this->amend_mail_sent,
            'amend_approve' => $this->amend_approve,
            'amend_status' => $this->amend_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
