<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestinationStoreRequest extends FormRequest
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
            'description' => 'nullable',
            'category_id' => 'nullable',
            'entry_fee' => 'nullable',
            'city_id' => 'nullable',
            'feature_img' => 'nullable',
            'summary' => 'nullable',
            'detail' => 'nullable',
            'place_id' => 'nullable',
            'placement_id' => 'nullable',
            'latitude' => 'nullable',
            'longitude' => 'nullable',
        ];
    }
}
