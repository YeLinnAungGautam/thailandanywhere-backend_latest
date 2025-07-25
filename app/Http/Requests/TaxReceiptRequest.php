<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_type' => 'required|string|in:App\Models\Hotel,App\Models\EntranceTicket', // Adjust as needed
            'product_id' => 'required|string',
            'company_legal_name' => 'required|string|max:255',
            'receipt_date' => 'required|date',
            'service_start_date' => 'required|date',
            'service_end_date' => 'required|date',
            'receipt_image' => 'nullable|image|max:2048', // Optional image, max 2MB
            'additional_codes' => 'nullable|json', // Optional JSON field for additional codes
            'total_tax_withold' => 'required|numeric|min:0',
            'total_tax_amount' => 'required|numeric|min:0',
            'total_after_tax' => 'required|numeric|min:0',
            // 'total' => 'required|numeric|min:0',
            'invoice_number' => 'required|string|max:255|unique:tax_receipts,invoice_number,' . $this->id . ',id', // Optional invoice number,id', // Optional invoice number
            'reservation_ids' => 'nullable|array', // Optional array of reservation IDs
            'reservation_ids.*' => 'exists:booking_items,id', // Each ID must exist in booking_items table
        ];
    }
}
