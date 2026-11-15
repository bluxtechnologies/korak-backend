<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property object{id: string, ip_address: ?string, user_agent: ?string, last_activity: int} $resource
 */
class SessionResource extends JsonResource
{
    /**
     * Transform the session object into a standardized API array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        $isCurrent = $currentSessionId !== null && hash_equals($this->resource->id, $currentSessionId);

        return [
            // Safe opaque identifier (SHA-256 hash) to avoid leaking the raw session cookie
            'id' => hash('sha256', $this->resource->id),
            'ip_address' => $this->resource->ip_address,
            'user_agent' => $this->resource->user_agent,
            'is_current' => $isCurrent,
            'last_activity' => Carbon::createFromTimestamp($this->resource->last_activity)->toISOString(),
        ];
    }
}
