<?php

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDedicatedAccountRequest extends FormRequest
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-()]+$/'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }
}
