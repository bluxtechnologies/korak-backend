<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuditEvent $resource
 */
class SecurityActivityResource extends JsonResource
{
    /**
     * Transform the audit event into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'event' => $this->resource->event->value,
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'metadata' => $this->resource->metadata,
            'occurred_at' => $this->resource->occurred_at->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
