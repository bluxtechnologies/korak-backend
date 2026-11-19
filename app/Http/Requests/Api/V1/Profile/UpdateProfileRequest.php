<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    /**
     * Prepare the data for validation.
     *
     * Handles composite `name` (splitting into `first_name` and `last_name`)
     * and ensures alias symmetry between `phone` and `phone_number`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('first_name')) {
            $parts = explode(' ', trim((string) $this->input('name')), 2);
            $this->merge([
                'first_name' => $parts[0] ?? null,
                'last_name' => $parts[1] ?? null,
            ]);
        }

        if ($this->filled('phone_number') && ! $this->filled('phone')) {
            $this->merge(['phone' => $this->input('phone_number')]);
        } elseif ($this->filled('phone') && ! $this->filled('phone_number')) {
            $this->merge(['phone_number' => $this->input('phone')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $customerId = $this->user('customer')?->getAuthIdentifier();

        return [
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('customers', 'username')->ignore($customerId),
            ],
            'first_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:60',
            ],
            'last_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:60',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\s\-()]+$/',
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9+\s\-()]+$/',
            ],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],
        ];
    }
}
