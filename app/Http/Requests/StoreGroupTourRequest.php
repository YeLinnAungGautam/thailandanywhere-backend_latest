<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupTourRequest extends FormRequest
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
            'name' => 'required',
            'price' => 'required',
            'sku_code' => 'required|' . Rule::unique('group_tours'),
        ];
    }

    public function messages()
    {
        return [
            'sku_code.unique' => 'SKU Code is already exist, please try another one',
            'price.required' => 'Price is required',
        ];
    }
}
