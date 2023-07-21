<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StorePrivateVanTourRequest extends FormRequest
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
            'name'  => 'required',
            'description' => 'required|string|max:225',
            'cover_image' => 'required|image|max:2048',
            'city_ids' => 'required|array',
            'car_ids' => 'required|array',
            'images' => 'required|array',
            'prices' => ['required', 'array', function ($attribute, $value, $fail) {
                if (count($value) !== count($this->input('car_ids'))) {
                    $fail($attribute . ' and cars must have the same number of elements.');
                }
            }],
            'agent_prices' => ['required', 'array', function ($attribute, $value, $fail) {
                if (count($value) !== count($this->input('car_ids'))) {
                    $fail($attribute . ' and cars must have the same number of elements.');
                }
            }],
            'tag_ids' => 'required|array',
            'destination_ids' => 'required|array',
            'sku_code' => 'required|' . Rule::unique('private_van_tours'),
        ];
    }

    public function messages()
    {
        return [
            'sku_code.unique' => 'SKU Code is already exist, please try another one',
            'cover_image.required' => 'Cover Image is required',
            'tag_ids.required' => 'Tags is required',
            'destination_ids.required' => 'Destinations is required',
            'city_ids.required' => 'Cities is required',
            'car_ids.required' => 'Cars is required',
            'prices.required' => 'Prices is required',
            'agent_prices.required' => 'Agent Prices is required',
            'car_ids.array' => 'Cars must be an array',
            'prices.array' => 'Prices must be an array',
            'agent_prices.array' => 'Agent Prices must be an array',
            'prices.Mismatched count' => 'Cars and Prices must have the same number of elements.',
            'agent_price.Mismatched count' => 'Cars and Agent Prices must have the same number of elements.',
        ];
    }
}
