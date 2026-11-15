<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'data';

    /**
     * Additional metadata to include in the response envelope.
     *
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * Set additional metadata for the response.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => empty($this->meta) ? (object) [] : $this->meta,
        ];
    }

    /**
     * Create a new resource collection instance.
     *
     * @param  mixed  $resource
     * @return ApiResourceCollection
     */
    protected static function newCollection($resource)
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
