<?php

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class InitiateDepositRequest extends FormRequest
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
            'amount' => ['nullable', 'numeric', 'min:1'],
            'amount_ngn' => ['nullable', 'numeric', 'min:1'],
            'in_kobo' => ['nullable', 'boolean'],
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
            'amount.min' => 'The minimum deposit amount is ₦100.00.',
            'amount_ngn.min' => 'The minimum deposit amount is ₦100.00.',
        ];
    }

    /**
     * Get the requested amount in kobo.
     */
    public function getAmountInKobo(): int
    {
        if ($this->has('amount_ngn')) {
            return (int) round((float) $this->input('amount_ngn') * 100);
        }

        $amount = (float) $this->input('amount', 0);

        if ($this->has('in_kobo')) {
            return $this->boolean('in_kobo') ? (int) round($amount) : (int) round($amount * 100);
        }

        if ($amount < 10000) {
            return (int) round($amount * 100);
        }

        return (int) round($amount);
    }
}
