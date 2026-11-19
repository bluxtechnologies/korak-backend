<?php

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SupportTicketMessage $resource
 */
class SupportTicketMessageResource extends JsonResource
{
    /**
     * Transform the message resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'ticket_id' => $this->resource->ticket->ulid,
            'author' => $this->resource->author_type,
            'author_name' => $this->resource->author_name,
            'body' => $this->resource->body,
            'created_at' => $this->resource->created_at->toISOString(),
        ];
    }
}
