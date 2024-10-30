<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
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
        // $supplierId = $this->route('supplier');

        $rules = [
            'name' => 'required',
            'contact' => 'required',
            'bank_name' => 'required',
            'bank_account_no' => 'required',
            'bank_account_name' => 'required',
            'password' => 'required'
        ];

        if ($this->method() == 'POST') {
            $rules['logo'] = 'required';
            $rules['email'] = 'required|email|unique:suppliers,email';
        } else {
            $rules['email'] = 'required|email|unique:suppliers,email,' . $this->id;

            // solution 2 for unique email validation
            // $rules['email'] = 'required|email|unique:suppliers,email,{$supplierId}';
        }

        return $rules;
    }
}
