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
            'pickup_location' => 'nullable|string',
            'dropoff_location' => 'nullable|string',
            'pickup_time' => 'nullable',
            'route_plan' => 'nullable|string',
            'special_request' => 'nullable|string',
            'is_driver_collect' => 'nullable|boolean',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'driver_info_id' => 'nullable|exists:driver_infos,id',
            'driver_contact' => 'nullable|string',
            'car_number' => 'nullable|string',
            'cost_price' => 'nullable|numeric',
            'total_cost_price' => 'nullable|numeric',
        ];
    }
}
