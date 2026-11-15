<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Wallet $resource
 */
class WalletResource extends JsonResource
{
    /**
     * Transform the wallet resource into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'currency' => $this->resource->currency,
            'balance' => $this->resource->balance,
            'balance_formatted' => number_format($this->resource->balance / 100, 2, '.', ''),
            'created_at' => $this->resource->created_at->toISOString(),
            'updated_at' => $this->resource->updated_at->toISOString(),
        ];
    }
}
