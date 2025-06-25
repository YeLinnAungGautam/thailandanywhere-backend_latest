<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationReceiptImageResource extends JsonResource
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
            'file' => $this->file ? Storage::url('images/' . $this->file) : null,
            'amount' => $this->amount,
            'bank_name' => $this->bank_name,
            'date' => isset($this->date) ? Carbon::parse($this->date)->format('d-m-Y H:i:s') : null,
            'is_corporate' => $this->is_corporate,
            'comment' => $this->comment,
            'sender' => $this->sender,
            'reciever' => $this->reciever,
            'interact_bank' => $this->interact_bank,
            'currency' => $this->currency,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
