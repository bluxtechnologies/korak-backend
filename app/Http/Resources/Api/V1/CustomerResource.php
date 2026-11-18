<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * @property-read Customer $resource
 */
class CustomerResource extends ApiResource
{
    /**
     * Transform the customer model into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim("{$this->resource->first_name} {$this->resource->last_name}");
        $displayName = $fullName !== '' ? $fullName : $this->resource->username;

        return [
            'id' => $this->resource->ulid,
            'username' => $this->resource->username,
            'name' => $displayName,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'full_name' => $displayName,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'phone_number' => $this->resource->phone_number ?? $this->resource->phone,
            'email_verified_at' => $this->resource->email_verified_at?->toIso8601String(),
            'has_virtual_account' => $this->resource->virtualAccount !== null,
            'virtual_account' => $this->resource->virtualAccount ? [
                'bank_name' => $this->resource->virtualAccount->bank_name,
                'bank_code' => $this->resource->virtualAccount->bank_code,
                'account_number' => $this->resource->virtualAccount->account_number,
                'account_name' => $this->resource->virtualAccount->account_name,
            ] : null,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
