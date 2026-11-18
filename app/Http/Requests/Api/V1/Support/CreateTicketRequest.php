<?php

namespace App\Http\Requests\Api\V1\Support;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:5', 'max:120'],
            'category' => ['required', 'string', 'in:billing,order,vpn,airtime_data,account,general'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
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
            'subject.min' => 'Subject must be at least 5 characters.',
            'subject.max' => 'Subject may not be greater than 120 characters.',
            'message.min' => 'Please describe your issue in at least 20 characters.',
            'message.max' => 'Message may not be greater than 2000 characters.',
        ];
    }
}
