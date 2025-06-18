<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BookingReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'note' => $this->note,
            'amount' => $this->amount,
            'sender' => $this->sender,
            'reciever' => $this->reciever,
            'interact_bank' => $this->interact_bank,
            'bank_name' => $this->bank_name,
            'date' => isset($this->date) ? Carbon::parse($this->date)->format('d-m-Y H:i:s') : null,
            'is_corporate' => $this->is_corporate,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
