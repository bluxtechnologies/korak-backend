<?php

namespace App\Http\Requests\Api\V1\Bills;

use Illuminate\Foundation\Http\FormRequest;

class PayBillRequest extends FormRequest
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
            'service_name' => ['required', 'string', 'max:128'],
            'category' => ['required', 'string', 'max:64'],
            'customer_identifier' => ['required', 'string', 'max:128'],
            'customer_name' => ['nullable', 'string', 'max:128'],
            'package_code' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'integer', 'min:1000'], // Minimum 10.00 NGN in kobo
            'face_amount' => ['nullable', 'integer', 'min:1000'],
        ];
    }
}
