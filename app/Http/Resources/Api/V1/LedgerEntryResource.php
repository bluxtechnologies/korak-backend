<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LedgerEntry $resource
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * Transform the ledger entry into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'direction' => $this->resource->direction->value,
            'type' => $this->resource->type->value,
            'amount' => $this->resource->amount,
            'amount_formatted' => number_format($this->resource->amount / 100, 2, '.', ''),
            'balance_after' => $this->resource->balance_after,
            'balance_after_formatted' => number_format($this->resource->balance_after / 100, 2, '.', ''),
            'currency' => $this->resource->currency,
            'reference' => $this->resource->reference,
            'description' => $this->resource->description,
            'metadata' => $this->resource->metadata,
            'created_at' => $this->resource->created_at->toISOString(),
        ];
    }
}
