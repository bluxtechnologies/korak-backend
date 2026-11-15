<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => (string) $this->id,
            'type' => class_basename((string) $this->type),
            'title' => isset($data['title']) ? (string) $data['title'] : null,
            'message' => isset($data['message']) ? (string) $data['message'] : null,
            'category' => isset($data['category']) ? (string) $data['category'] : 'general',
            'data' => $data,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
