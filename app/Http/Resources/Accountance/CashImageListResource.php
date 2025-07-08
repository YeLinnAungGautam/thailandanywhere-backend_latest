<?php

namespace App\Http\Resources\Accountance;

use App\Http\Resources\BookingItemGroupResource;
use App\Http\Resources\BookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CashImageListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $relatable = null;

        // Check if relatable relationship exists before processing
        if ($this->relatable) {
            switch ($this->relatable_type) {
                case 'App\Models\Booking':
                    $relatable = new BookingResource($this->relatable);
                    break;
                case 'App\Models\BookingItemGroup':
                    $relatable = new BookingItemGroupResource($this->relatable);
                    break;
                case 'App\Models\CashBook':
                    $relatable = new CashBookResource($this->relatable);
                    break;
                default:
                    // Handle unknown relatable types
                    $relatable = null;
            }
        }

        return [
            'id' => $this->id,
            'image' => $this->image ? Storage::url('images/' . $this->image) : null,
            'date' => $this->date ? $this->date->format('d-m-Y H:i:s') : null,
            'created_at' => $this->created_at ? $this->created_at->format('d-m-Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d-m-Y H:i:s') : null,
            'sender' => $this->sender,
            'receiver' => $this->receiver, // Fixed typo: 'reciever' -> 'receiver'
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interact_bank' => $this->interact_bank,
            'relatable_type' => $this->relatable_type,
            'relatable_id' => $this->relatable_id,
            'relatable' => $relatable,
        ];
    }
}
