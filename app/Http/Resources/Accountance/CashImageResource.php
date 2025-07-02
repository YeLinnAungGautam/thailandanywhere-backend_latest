<?php

namespace App\Http\Resources\Accountance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashImageResource extends JsonResource
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
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'date' => $this->date ? $this->date->format('d-m-Y H:i:s') : null,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
            'sender' => $this->sender,
            'reciever' => $this->receiver,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interact_bank' => $this->interact_bank,
            'relatable_type' => $this->relatable_type,
            'relatable_id' => $this->relatable_id
        ];
    }
}
