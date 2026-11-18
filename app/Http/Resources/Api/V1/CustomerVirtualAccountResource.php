<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\CustomerVirtualAccount;
use Illuminate\Http\Request;

/**
 * @property-read CustomerVirtualAccount $resource
 */
class CustomerVirtualAccountResource extends ApiResource
{
    /**
     * Transform the virtual account into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'provider' => $this->resource->provider,
            'account_reference' => $this->resource->account_reference,
            'bank_name' => $this->resource->bank_name,
            'bank_code' => $this->resource->bank_code,
            'account_number' => $this->resource->account_number,
            'account_name' => $this->resource->account_name,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at->toISOString(),
        ];
    }
}
