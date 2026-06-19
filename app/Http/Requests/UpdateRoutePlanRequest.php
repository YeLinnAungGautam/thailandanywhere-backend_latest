<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutePlanRequest extends FormRequest
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
            'vantour_ids' => 'sometimes|array|min:1',
            'vantour_ids.*' => 'integer|exists:private_van_tours,id',

            'destination_ids' => 'sometimes|array',
            'destination_ids.*' => 'integer|exists:destinations,id',

            'city_ids' => 'sometimes|array',
            'city_ids.*' => 'integer|exists:cities,id',

            'main_cover_photo' => 'nullable|image|max:5120',
            'other_photos' => 'nullable|array',
            'other_photos.*' => 'image|max:5120',

            'english_description' => 'nullable|string',
            'mm_description' => 'nullable|string',
            'route' => 'nullable|string'
        ];
    }
}
