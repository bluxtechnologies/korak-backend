<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

test('success response returns locked data and meta envelope', function (): void {
    $response = ApiResponse::success(['id' => '01JNGZ1EXAMPLE']);

    expect($response->status())->toBe(200);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['data', 'meta'])
        ->and($json['data'])->toBe(['id' => '01JNGZ1EXAMPLE'])
        ->and($json['meta'])->toBe([]);
});

test('success response with null data defaults to an empty object', function (): void {
    $response = ApiResponse::success();

    expect($response->status())->toBe(200);

    $json = $response->getData(true);
    expect($json['data'])->toBe([])
        ->and($json['meta'])->toBe([]);

    // Raw JSON string must encode empty associative arrays/objects as {}
    expect($response->getContent())->toBe('{"data":{},"meta":{}}');
});

test('success response merges custom metadata', function (): void {
    $response = ApiResponse::success(['status' => 'active'], ['version' => '1.0']);

    $json = $response->getData(true);
    expect($json['meta'])->toBe(['version' => '1.0']);
});

test('paginated response returns locked data and pagination meta', function (): void {
    $items = collect([
        ['id' => '1', 'name' => 'First'],
        ['id' => '2', 'name' => 'Second'],
    ]);

    $paginator = new LengthAwarePaginator(
        items: $items,
        total: 120,
        perPage: 15,
        currentPage: 1
    );

    $response = ApiResponse::paginated($paginator);

    expect($response->status())->toBe(200);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['data', 'meta'])
        ->and($json['data'])->toBe([
            ['id' => '1', 'name' => 'First'],
            ['id' => '2', 'name' => 'Second'],
        ])
        ->and($json['meta'])->toBe([
            'current_page' => 1,
            'per_page' => 15,
            'total' => 120,
            'last_page' => 8,
        ])
        ->and($json)->not->toHaveKey('links');
});

test('error response returns locked message and code without errors key by default', function (): void {
    $response = ApiResponse::error('Resource not found.', 'not_found', 404);

    expect($response->status())->toBe(404);

    $json = $response->getData(true);
    expect($json)->toBe([
        'message' => 'Resource not found.',
        'code' => 'not_found',
    ])->not->toHaveKey('errors');
});

test('error response includes errors key for validation failures', function (): void {
    $errors = [
        'email' => ['The email field is required.'],
    ];

    $response = ApiResponse::error('Validation failed.', 'validation_failed', 422, $errors);

    expect($response->status())->toBe(422);

    $json = $response->getData(true);
    expect($json)->toBe([
        'message' => 'Validation failed.',
        'code' => 'validation_failed',
        'errors' => [
            'email' => ['The email field is required.'],
        ],
    ]);
});

test('no content response returns 204 with empty body', function (): void {
    $response = ApiResponse::noContent();

    expect($response->status())->toBe(204)
        ->and($response->getContent())->toBe('');
});
