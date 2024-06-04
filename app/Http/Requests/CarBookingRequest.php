<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CarBookingRequest extends FormRequest
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
            'supplier_id' => 'nullable',
            'driver_id' => 'nullable',
            'driver_info_id' => 'nullable',
            'cost_price' => 'nullable|integer',
            'total_cost_price' => 'nullable|integer'
        ];
    }
}
