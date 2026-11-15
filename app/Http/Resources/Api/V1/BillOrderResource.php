<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\BillOrder;
use Illuminate\Http\Request;

/**
 * @mixin BillOrder
 */
class BillOrderResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'reference' => $this->reference,
            'provider_reference' => $this->provider_reference,
            'category' => $this->category,
            'service_code' => $this->service_code,
            'service_name' => $this->service_name,
            'package_code' => $this->package_code,
            'customer_identifier' => $this->customer_identifier,
            'customer_name' => $this->customer_name,
            'amount' => $this->amount,
            'amount_formatted' => number_format($this->amount / 100, 2),
            'face_amount' => $this->face_amount,
            'face_amount_formatted' => number_format($this->face_amount / 100, 2),
            'discount_amount' => $this->discount_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'token' => $this->token,
            'metadata' => $this->metadata,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
