<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $user = Auth::user();

        return [
            'email' => ['email', 'max:255', Rule::unique('users')->ignore($user)],
            'name' => 'nullable',
            'first_name' => 'nullable',
            'last_name' => 'nullable',
            'phone' => 'nullable',
            'address' => 'nullable',
            'gender' => 'nullable',
            'dob' => 'nullable',
            'profile' => 'nullable'
        ];
    }
}
