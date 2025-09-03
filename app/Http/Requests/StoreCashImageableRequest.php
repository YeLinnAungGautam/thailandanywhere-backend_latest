<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashImageableRequest extends FormRequest
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
            'cash_image' => 'nullable|image|max:5120',

            'image' => 'required_with:cash_image',
            'date' => 'required_with:cash_image|date_format:Y-m-d H:i:s',
            'sender' => 'required_with:cash_image|string|max:255',
            'receiver' => 'required_with:cash_image|string|max:255',
            'amount' => 'required_with:cash_image|numeric|min:0',
            'interact_bank' => 'nullable|string|max:255',
            'currency' => 'required_with:cash_image|string|max:10',

            'cash_image_id' => 'nullable|exists:cash_images,id',
            'targets' => 'required|array',
            'targets.*.model_type' => 'required|string|in:booking,cash_book,booking_item_group',
            'targets.*.model_id' => 'required|integer',
            'type' => 'nullable|string|max:255',
            'deposit' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ];
    }
}
