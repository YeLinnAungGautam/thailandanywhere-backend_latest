<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promoId = $this->route('promo')?->promo_id;

        return [
            'promo_name'       => 'sometimes|string|max:255',
            'promo_des'        => 'nullable|string',
            'promo_code'       => [
                'sometimes', 'string', 'max:50',
                Rule::unique('promos', 'promo_code')->ignore($promoId, 'promo_id'),
            ],
            'promo_type'       => 'sometimes|in:fixed,percent',
            'promo_amount'     => 'sometimes|numeric|min:0',
            'promo_count'      => 'sometimes|integer|min:1',
            'promo_active'     => 'boolean',
            'promo_start_date' => 'nullable|date',
            'promo_end_date'   => 'sometimes|date',

            'promo_applies_to' => 'sometimes|in:all,specific',

            'hotel_ids'             => 'nullable|array',
            'hotel_ids.*'           => 'integer|exists:hotels,id',
            'entrance_ticket_ids'   => 'nullable|array',
            'entrance_ticket_ids.*' => 'integer|exists:entrance_tickets,id',
            'vantour_ids'           => 'nullable|array',
            'vantour_ids.*'         => 'integer|exists:private_van_tours,id',
            'inclusive_ids'         => 'nullable|array',
            'inclusive_ids.*'       => 'integer|exists:group_tours,id',

            'all_hotels'           => 'boolean',
            'all_entrance_tickets' => 'boolean',
            'all_vantours'         => 'boolean',
            'all_inclusive'        => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'promo_code.unique' => 'This coupon code already exists, please use a different code.',
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('promo_applies_to') !== 'specific') {
                return;
            }

            $hasSelection =
                $this->boolean('all_hotels') || filled($this->input('hotel_ids')) ||
                $this->boolean('all_entrance_tickets') || filled($this->input('entrance_ticket_ids')) ||
                $this->boolean('all_vantours') || filled($this->input('vantour_ids')) ||
                $this->boolean('all_inclusive') || filled($this->input('inclusive_ids'));

            if (! $hasSelection) {
                $validator->errors()->add(
                    'promo_applies_to',
                    'When "specific" is selected, at least one product type or product id must be selected.'
                );
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
