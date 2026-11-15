<?php

namespace App\Http\Requests\Api\V1\Bills;

use Illuminate\Foundation\Http\FormRequest;

class ValidateBillRequest extends FormRequest
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
            'service_code' => ['required', 'string', 'max:64'],
            'customer_identifier' => ['required', 'string', 'max:128'],
        ];
    }
}
