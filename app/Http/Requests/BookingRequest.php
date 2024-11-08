<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
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
            'customer_id' => 'required',
            'sold_from' => 'required|string',
            'payment_method' => 'required|string',
            'bank_name' => 'required|string',
            'payment_status' => 'required|string',
            'booking_date' => 'required|string',
            'items' => 'required',
            'sub_total' => 'required|integer',
            'grand_total' => 'required|integer',
            'balance_due' => 'required',
            'balance_due_date' => 'required',
            'transfer_code' => 'nullable|in:MMTT,TT,INTT'
        ];
    }
}
