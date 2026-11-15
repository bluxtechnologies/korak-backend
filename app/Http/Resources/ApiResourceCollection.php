<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

class ApiResourceCollection extends ResourceCollection
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
     * Create a new resource collection instance.
     */
    public function __construct(mixed $resource, ?string $collects = null)
    {
        if ($collects !== null) {
            $this->collects = $collects;
        }

        parent::__construct($resource);
    }

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
     * Customize the pagination information for the resource.
     *
     * Overrides default Laravel pagination links/meta to strictly match Doc 06:
     * { "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $paginationMeta = [
            'current_page' => $paginated['current_page'] ?? null,
            'per_page' => $paginated['per_page'] ?? null,
            'total' => $paginated['total'] ?? null,
            'last_page' => $paginated['last_page'] ?? null,
        ];

        return [
            'meta' => array_merge($paginationMeta, $this->meta),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        if ($this->resource instanceof AbstractPaginator || $this->resource instanceof AbstractCursorPaginator) {
            return [];
        }

        return [
            'meta' => empty($this->meta) ? (object) [] : $this->meta,
        ];
    }
}
