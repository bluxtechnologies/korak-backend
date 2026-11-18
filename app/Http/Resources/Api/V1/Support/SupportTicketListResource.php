<?php

namespace App\Http\Resources\Api\V1\Support;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property-read SupportTicket $resource
 */
class SupportTicketListResource extends JsonResource
{
    /**
     * Transform the ticket resource into an array for listing.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latest = $this->resource->relationLoaded('latestMessage')
            ? $this->resource->latestMessage
            : ($this->resource->relationLoaded('messages')
                ? $this->resource->messages->sortByDesc('id')->first()
                : $this->resource->latestMessage);

        $preview = $latest ? Str::limit($latest->body, 80) : null;

        return [
            'id' => $this->resource->ulid,
            'reference' => $this->resource->reference,
            'subject' => $this->resource->subject,
            'category' => $this->resource->category->value,
            'status' => $this->resource->status->value,
            'message_count' => (int) ($this->resource->messages_count ?? $this->resource->messages()->count()),
            'has_unread_reply' => (bool) $this->resource->has_unread_reply,
            'latest_message_preview' => $preview,
            'created_at' => $this->resource->created_at->toISOString(),
            'updated_at' => $this->resource->updated_at->toISOString(),
        ];
    }
}
