<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverRequest extends FormRequest
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
        $rules = [
            'name' => 'required',
            'contact' => 'required',
            'vendor_name' => 'required',
        ];

        if($this->method() == 'POST') {
            $rules['profile'] = 'required';
            $rules['car_photo'] = 'required';
        }

        return $rules;
    }
}
