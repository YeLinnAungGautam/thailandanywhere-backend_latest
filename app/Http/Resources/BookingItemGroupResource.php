<?php

namespace App\Http\Resources;

use App\Http\Resources\Accountance\BookingItemResource;
use App\Http\Resources\BookingItemGroup\CustomerDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemGroupResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'product_type' => $this->product_type,
            'total_cost_price' => $this->total_cost_price,
            'invoice_sender' => $this->invoice_sender,
            'invoice_date' => $this->invoice_date,
            'invoice_due_date' => $this->invoice_due_date,
            'invoice_amount' => $this->invoice_amount,

            'sent_booking_request' => $this->sent_booking_request,
            'booking_request_proof' => get_file($this->booking_request_proof, 'booking_item_groups'),
            // 'booking_confirm_letter' => $this->customerDocuments->contains('type', 'booking_confirm_letter') ? CustomerDocumentResource::collection($this->customerDocuments->where('type', 'booking_confirm_letter')) : [],
            // 'tax_credit' => $this->relationLoaded('taxReceipts') && $this->taxReceipts->count() > 0
            // ? TaxReceiptResource::collection($this->taxReceipts)
            // : [],

            'booking_confirm_letter' => $this->relationLoaded('customerDocuments') && $this->customerDocuments->contains('type', 'booking_confirm_letter')
                ? CustomerDocumentResource::collection($this->customerDocuments->where('type', 'booking_confirm_letter'))
                : [],

            // Fix: Use consistent pattern with relationLoaded check
            'tax_credit' => $this->relationLoaded('taxReceipts') && $this->taxReceipts->count() > 0
                ? TaxReceiptResource::collection($this->taxReceipts)
                : [],

            'passport_info' => $this->passport_info,
            'expense_method' => $this->expense_method,
            'expense_status' => $this->expense_status,
            'expense_bank_name' => $this->expense_bank_name,
            'expense_bank_account' => $this->expense_bank_account,
            'sent_expense_mail' => $this->sent_expense_mail,
            'expense_mail_proof' => get_file($this->expense_mail_proof, 'booking_item_groups'),
            'expense_total_amount' => $this->expense_total_amount,
            'confirmation_status' => $this->confirmation_status,
            'confirmation_code' => $this->confirmation_code,
            'confirmation_image' => get_file($this->confirmation_image, 'booking_item_groups'),

            'booking' => new BookingResource($this->whenLoaded('booking')),

            'items' => BookingItemResource::collection($this->whenLoaded('bookingItems')),
        ];
    }
}
