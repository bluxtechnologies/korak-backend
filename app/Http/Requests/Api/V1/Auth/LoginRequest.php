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
            'login' => ['required_without_all:email,username', 'nullable', 'string'],
            'email' => ['required_without_all:login,username', 'nullable', 'string'],
            'username' => ['required_without_all:login,email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Resolve the login identifier (username or email).
     */
    public function identifier(): string
    {
        return (string) ($this->input('login')
            ?? $this->input('email')
            ?? $this->input('username'));
    }
}
