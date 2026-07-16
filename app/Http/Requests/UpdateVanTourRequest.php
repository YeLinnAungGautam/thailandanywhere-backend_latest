<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVanTourRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'sku_code' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:100',
            'supplier_cost' => 'nullable|array',

            'city_ids' => 'nullable|array',
            'city_ids.*' => 'integer|exists:cities,id',

            'route_plan_ids' => 'nullable|array',
            'route_plan_ids.*' => 'integer|exists:route_plans,id',

            'car_ids' => 'nullable|array',
            'car_ids.*' => 'integer|exists:cars,id',
            'prices' => 'nullable|array',
            'prices.*' => 'numeric|min:0',
            'agent_prices' => 'nullable|array',
            'agent_prices.*' => 'numeric|min:0',
            'costs' => 'nullable|array',
            'costs.*' => 'numeric|min:0',
        ];
    }
}
