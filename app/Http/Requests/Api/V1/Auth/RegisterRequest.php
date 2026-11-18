<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                'unique:customers,username',
            ],
            'phone_number' => [
                'required',
                'string',
                'min:7',
                'max:20',
                'regex:/^[0-9+\s\-().]+$/',
                'unique:customers,phone_number',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:customers,email',
            ],
            'password' => [
                'required',
                'string',
                Password::min(8),
                'confirmed',
            ],
            'terms' => [
                'sometimes',
                'boolean',
            ],
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
            'username.regex' => 'The username may only contain letters, numbers, dashes, underscores, and periods.',
            'phone_number.regex' => 'The phone number format is invalid.',
            'phone_number.unique' => 'The phone number has already been taken.',
        ];
    }
}
