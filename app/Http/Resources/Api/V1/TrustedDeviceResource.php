<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\CustomerTrustedDevice;
use Illuminate\Http\Request;

/**
 * @property-read CustomerTrustedDevice $resource
 */
class TrustedDeviceResource extends ApiResource
{
    /**
     * Transform the trusted device model into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'device_name' => $this->resource->device_name,
            'ip_address' => $this->resource->ip_address,
            'last_used_at' => $this->resource->last_used_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
