<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendDeviceCodeRequest extends FormRequest
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
            'challenge_token' => ['required_without_all:email,login,phone_number', 'nullable', 'string'],
            'email' => ['nullable', 'string'],
            'login' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
