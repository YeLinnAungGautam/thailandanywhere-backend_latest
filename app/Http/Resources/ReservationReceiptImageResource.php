<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            // 'date' => $this->date->format('d-m-Y H:i:s'),
            'is_corporate' => $this->is_corporate,
            'comment' => $this->comment,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
