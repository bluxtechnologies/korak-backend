<?php

namespace Tests\Feature;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DummyResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
        ];
    }
}

it('formats a single resource into the locked success envelope', function (): void {
    $resource = new DummyResource(['id' => 'abc', 'title' => 'Test Item']);
    $response = $resource->toResponse(Request::create('/api/v1/dummy'));

    expect($response->status())->toBe(200);

    $json = $response->getData(true);
    expect($json)->toBe([
        'data' => [
            'id' => 'abc',
            'title' => 'Test Item',
        ],
        'meta' => [],
    ]);
});

it('formats a single resource with custom meta', function (): void {
    $resource = (new DummyResource(['id' => 'abc', 'title' => 'Test Item']))
        ->withMeta(['source' => 'test']);

    $response = $resource->toResponse(Request::create('/api/v1/dummy'));
    $json = $response->getData(true);

    expect($json['meta'])->toBe(['source' => 'test']);
});

it('formats a collection into the locked envelope', function (): void {
    $items = [
        ['id' => '1', 'title' => 'Item 1'],
        ['id' => '2', 'title' => 'Item 2'],
    ];

    $collection = DummyResource::collection(collect($items));
    $response = $collection->toResponse(Request::create('/api/v1/dummy'));

    $json = $response->getData(true);
    expect($json['data'])->toBe([
        ['id' => '1', 'title' => 'Item 1'],
        ['id' => '2', 'title' => 'Item 2'],
    ])->and($json['meta'])->toBe([]);
});

it('formats a paginated collection into the locked envelope without links', function (): void {
    $items = collect([
        ['id' => '1', 'title' => 'Item 1'],
        ['id' => '2', 'title' => 'Item 2'],
    ]);

    $paginator = new LengthAwarePaginator($items, 25, 10, 1);

    $collection = DummyResource::collection($paginator);
    $response = $collection->toResponse(Request::create('/api/v1/dummy'));

    $json = $response->getData(true);

    expect($json)->toHaveKeys(['data', 'meta'])
        ->and($json['meta'])->toBe([
            'current_page' => 1,
            'per_page' => 10,
            'total' => 25,
            'last_page' => 3,
        ])
        ->and($json)->not->toHaveKey('links');
});
