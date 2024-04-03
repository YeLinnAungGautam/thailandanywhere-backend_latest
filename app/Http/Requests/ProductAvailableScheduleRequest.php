<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductAvailableScheduleRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'product_type' => 'required|string',
            'product_id' => 'required|string',
            'variable_id' => 'required|string',
            'quantity' => 'required|numeric',
            'date' => 'nullable|date|date_format:Y-m-d',
            'checkin_date' => 'nullable|date|date_format:Y-m-d',
            'checkin_date' => 'nullable|date|date_format:Y-m-d',
        ];
    }
}
