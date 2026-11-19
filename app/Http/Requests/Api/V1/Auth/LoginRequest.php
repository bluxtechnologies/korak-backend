<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('identifier') && ! $this->filled('login') && ! $this->filled('email') && ! $this->filled('username')) {
            $this->merge(['login' => $this->input('identifier')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['sometimes', 'nullable', 'string'],
            'login' => ['required_without_all:email,username,phone_number,phone,identifier', 'nullable', 'string'],
            'email' => ['required_without_all:login,username,phone_number,phone,identifier', 'nullable', 'string'],
            'username' => ['required_without_all:login,email,phone_number,phone,identifier', 'nullable', 'string'],
            'phone_number' => ['required_without_all:login,email,username,phone,identifier', 'nullable', 'string'],
            'phone' => ['required_without_all:login,email,username,phone_number,identifier', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Resolve the login identifier (phone_number, username, or email).
     */
    public function identifier(): string
    {
        return (string) ($this->input('login')
            ?? $this->input('identifier')
            ?? $this->input('email')
            ?? $this->input('username')
            ?? $this->input('phone_number')
            ?? $this->input('phone'));
    }
}
