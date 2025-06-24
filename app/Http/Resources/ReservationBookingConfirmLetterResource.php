<?php

namespace App\Http\Resources;

use App\Http\Resources\Cart\EntranceTicketCartResource;
use App\Http\Resources\Cart\HotelCartResource;
use Carbon\Carbon;
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

        $productResource = match ($this->product_type) {
            'App\Models\EntranceTicket' => new EntranceTicketCartResource($this->product),
            'App\Models\Hotel' => new HotelCartResource($this->product),
            default => null,
        };

        return [
            'id' => $this->id,
            'booking_item_id' => $this->booking_item_id,
            'amount' => $this->amount,
            'invoice' => $this->invoice ? Carbon::parse($this->invoice)->format('d-m-Y H:i:s') : null,
            'due_date' => $this->due_date ? Carbon::parse($this->due_date)->format('d-m-Y H:i:s') : null,
            'customer' => $this->customer,
            'sender_name' => $this->sender_name,
            'file' => $this->file ? Storage::url('images/' . $this->file) : null,
            'product_type' => $this->product_type,
            'product_id' => $this->product_id,
            'company_legal_name' => $this->company_legal_name,
            'receipt_date' => $this->receipt_date ? Carbon::parse($this->receipt_date)->format('d-m-Y H:i:s') : null,
            'service_start_date' => $this->service_start_date ? Carbon::parse($this->service_start_date)->format('d-m-Y H:i:s') : null,
            'service_end_date' => $this->service_end_date ? Carbon::parse($this->service_end_date)->format('d-m-Y H:i:s') : null,
            'total_tax_withold' => $this->total_tax_withold,
            'total_before_tax' => $this->total_before_tax,
            'total_after_tax' => $this->total_after_tax,
            'product' => $productResource,
            'created_at' => $this->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $this->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
