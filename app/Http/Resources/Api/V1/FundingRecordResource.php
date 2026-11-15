<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FundingRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FundingRecord $resource
 */
class FundingRecordResource extends JsonResource
{
    /**
     * Transform the funding record into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'reference' => $this->resource->reference,
            'provider' => $this->resource->provider,
            'amount' => $this->resource->amount,
            'amount_formatted' => number_format($this->resource->amount / 100, 2, '.', ''),
            'currency' => $this->resource->currency,
            'status' => $this->resource->status->value,
            'checkout_url' => $this->resource->checkout_url,
            'processed_at' => $this->resource->processed_at?->toISOString(),
            'created_at' => $this->resource->created_at->toISOString(),
        ];
    }
}
