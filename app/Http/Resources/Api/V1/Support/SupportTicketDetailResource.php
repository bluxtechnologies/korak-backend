<?php

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SupportTicket $resource
 */
class SupportTicketDetailResource extends JsonResource
{
    /**
     * Transform the ticket resource into a detailed array with message thread.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'reference' => $this->resource->reference,
            'subject' => $this->resource->subject,
            'category' => $this->resource->category->value,
            'status' => $this->resource->status->value,
            'message_count' => (int) ($this->resource->messages_count ?? $this->resource->messages()->count()),
            'has_unread_reply' => (bool) $this->resource->has_unread_reply,
            'created_at' => $this->resource->created_at->toISOString(),
            'updated_at' => $this->resource->updated_at->toISOString(),
            'messages' => SupportTicketMessageResource::collection(
                $this->resource->relationLoaded('messages')
                    ? $this->resource->messages
                    : $this->resource->messages()->oldest('id')->get()
            ),
        ];
    }
}
