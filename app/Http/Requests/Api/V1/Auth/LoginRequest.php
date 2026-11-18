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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required_without_all:email,username,phone_number,phone', 'nullable', 'string'],
            'email' => ['required_without_all:login,username,phone_number,phone', 'nullable', 'string'],
            'username' => ['required_without_all:login,email,phone_number,phone', 'nullable', 'string'],
            'phone_number' => ['required_without_all:login,email,username,phone', 'nullable', 'string'],
            'phone' => ['required_without_all:login,email,username,phone_number', 'nullable', 'string'],
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
            ?? $this->input('email')
            ?? $this->input('username')
            ?? $this->input('phone_number')
            ?? $this->input('phone'));
    }
}
