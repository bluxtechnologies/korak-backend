<?php

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class FundWalletRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Amount in minor units (kobo). Minimum 10,000 kobo (100.00 NGN).
            'amount' => ['required', 'integer', 'min:10000', 'max:500000000'],
            'redirect_url' => ['nullable', 'url'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum funding amount is 10,000 kobo (₦100.00).',
            'amount.max' => 'The maximum funding amount per transaction is ₦5,000,000.00.',
        ];
    }
}
