<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelRequest extends FormRequest
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
            'type' => 'nullable|in:direct_booking,other_booking',
            'rating' => 'nullable|integer',
            'nearby_places' => 'nullable|array',
            'category_id' => 'nullable|numeric',
            // 'email' => 'nullable|email',
        ];
    }
}
