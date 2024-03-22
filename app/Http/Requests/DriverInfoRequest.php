<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverInfoRequest extends FormRequest
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
        if($this->method() === 'POST') {
            return [
                'car_number' => 'required|unique:driver_infos,car_number',
                'is_default' => 'required'
            ];
        } else {
            return [
                'car_number' => 'required|unique:driver_infos,car_number,' . request()->info,
                'is_default' => 'required'
            ];
        }
    }
}
