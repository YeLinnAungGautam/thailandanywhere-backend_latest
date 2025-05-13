<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingItemGroupRequest extends FormRequest
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
            'invoice_sender' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'invoice_due_date' => 'nullable|date',
            'invoice_amount' => 'nullable|numeric',
            'sent_booking_request' => 'nullable|boolean',
            'booking_request_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'passport_info' => 'nullable|array',
            'expense_method' => 'nullable|string|max:255',
            'expense_status' => 'nullable|string|max:255',
            'expense_bank_name' => 'nullable|string|max:255',
            'expense_bank_account' => 'nullable|string|max:255',
            'sent_expense_mail' => 'nullable|boolean',
            'expense_mail_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'expense_total_amount' => 'nullable|numeric',
            'confirmation_status' => 'nullable|string|max:255',
            'confirmation_code' => 'nullable|string|max:255',
            'confirmation_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
    }
}
