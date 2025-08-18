<?php

namespace App\Http\Resources;

use App\Http\Resources\Cart\EntranceTicketCartResource;
use App\Http\Resources\Cart\HotelCartResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TaxReceiptResource extends JsonResource
{


    public function toArray(Request $request): array
    {

        $productResource = match ($this->product_type) {
            'App\Models\EntranceTicket' => new EntranceTicketCartResource($this->product),
            'App\Models\Hotel' => new HotelCartResource($this->product),
            default => null,
        };

        return [
            'id' => $this->id,
            'product_type' => $this->product_type,
            'product_id' => $this->product_id,
            'company_legal_name' => $this->company_legal_name,
            'receipt_date' => $this->receipt_date->toDateString(),
            'service_start_date' => $this->service_start_date->toDateString(),
            'service_end_date' => $this->service_end_date->toDateString(),
            'receipt_image' => $this->receipt_image ? Storage::url('images/'. $this->receipt_image ) : null,
            'additional_codes' => $this->additional_codes,
            'total_tax_withold' => $this->total_tax_withold,
            'total_tax_amount' => $this->total_tax_amount,
            'total_after_tax' => $this->total_after_tax,
            // 'total' => $this->total,
            'invoice_number' => $this->invoice_number,
            'groups' => $this->whenLoaded('groups'),
            'product' => $productResource,
            'declaration' => $this->declaration,
            'complete_print' => $this->complete_print,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
