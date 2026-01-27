<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => 'nullable|in:direct_booking,other_booking',
            'city_id' => 'required|exists:cities,id',
            'place' => 'nullable|string|max:255',
            'rating' => 'nullable|integer|min:1|max:5',
            'nearby_places' => 'nullable|array',
            'nearby_places.*.name' => 'required_with:nearby_places|string',
            'nearby_places.*.distance' => 'required_with:nearby_places|string',
            'nearby_places.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'category_id' => 'nullable',
            'vat_inclusion' => 'required',
            'contracts' => 'required|array|min:1',
            'contracts.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'facilities' => 'nullable|array',
            'facilities.*' => 'exists:facilities,id',
            'description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'full_description_en' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'place_id' => 'nullable|string',
            'legal_name' => 'nullable|string',
            'contract_due' => 'nullable|date',
            'location_map_title' => 'nullable|string',
            'location_map' => 'nullable|string',
            'youtube_link' => 'nullable|array',
            'email' => 'nullable|array',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'cancellation_policy' => 'nullable|string',
            'official_address' => 'nullable|string',
            'official_phone_number' => 'nullable|string',
            'official_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'official_email' => 'nullable|email',
            'official_remark' => 'nullable|string',
            'vat_id' => 'nullable|string',
            'vat_name' => 'nullable|string',
            'vat_address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Hotel name is required',
            'city_id.required' => 'City is required',
            'city_id.exists' => 'Selected city does not exist',
            'vat_inclusion.required' => 'VAT inclusion status is required',
            'contracts.required' => 'At least one contract file is required',
            'contracts.min' => 'At least one contract file is required',
        ];
    }
}
