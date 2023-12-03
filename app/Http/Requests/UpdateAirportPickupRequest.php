<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAirportPickupRequest extends FormRequest
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
            'name'  => 'sometimes',
            'car_ids' => 'sometimes|array',
            'prices' => ['sometimes', 'array', function ($attribute, $value, $fail) {
                if (count($value) !== count($this->input('car_ids'))) {
                    $fail($attribute . ' and cars must have the same number of elements.');
                }
            }],
            'agent_prices' => ['sometimes', 'array', function ($attribute, $value, $fail) {
                if (count($value) !== count($this->input('car_ids'))) {
                    $fail($attribute . ' and cars must have the same number of elements.');
                }
            }],
        ];
    }

    public function messages()
    {
        return [
            'car_ids.array' => 'Cars must be an array',
            'prices.array' => 'Prices must be an array',
            'agent_prices.array' => 'Agent Prices must be an array',
            'prices.Mismatched count' => 'Cars and Prices must have the same number of elements.',
            'agent_price.Mismatched count' => 'Cars and Agent Prices must have the same number of elements.',
        ];
    }
}
