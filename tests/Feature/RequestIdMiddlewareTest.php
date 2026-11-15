<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::prefix('api/v1/test-request-id')->group(function (): void {
        Route::get('/ping', function (): array {
            return [
                'context_id' => Context::get('request_id'),
                'attribute_id' => request()->attributes->get('request_id'),
            ];
        });

        Route::get('/error', function (): void {
            abort(404, 'Test not found');
        });

        Route::get('/server-error', function (): void {
            throw new \RuntimeException('Internal failure for request ID test');
        });
    });
});

it('generates a valid ULID X-Request-ID when none is provided', function (): void {
    $response = $this->get('/api/v1/test-request-id/ping');

    $response->assertOk()
        ->assertHeader('X-Request-ID');

    $requestId = (string) $response->headers->get('X-Request-ID');
    expect($requestId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');

    $data = $response->json();
    expect($data['context_id'])->toBe($requestId)
        ->and($data['attribute_id'])->toBe($requestId);
});

it('preserves a valid client-provided X-Request-ID', function (): void {
    $customId = 'client-req-abc-123.test_456';

    $response = $this->withHeader('X-Request-ID', $customId)
        ->get('/api/v1/test-request-id/ping');

    $response->assertOk()
        ->assertHeader('X-Request-ID', $customId);

    $data = $response->json();
    expect($data['context_id'])->toBe($customId)
        ->and($data['attribute_id'])->toBe($customId);
});

it('replaces an invalid client-provided X-Request-ID with a generated ULID', function (): void {
    $invalidId = "bad\r\nid with spaces & symbols";

    $response = $this->withHeader('X-Request-ID', $invalidId)
        ->get('/api/v1/test-request-id/ping');

    $response->assertOk();

    $headerId = (string) $response->headers->get('X-Request-ID');
    expect($headerId)->not->toBe($invalidId)
        ->and($headerId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('replaces an excessively long X-Request-ID with a generated ULID', function (): void {
    $oversizedId = str_repeat('a', 150);

    $response = $this->withHeader('X-Request-ID', $oversizedId)
        ->get('/api/v1/test-request-id/ping');

    $response->assertOk();

    $headerId = (string) $response->headers->get('X-Request-ID');
    expect($headerId)->not->toBe($oversizedId)
        ->and($headerId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('attaches X-Request-ID to error responses', function (): void {
    $response = $this->get('/api/v1/test-request-id/error');

    $response->assertNotFound()
        ->assertHeader('X-Request-ID');
});

it('matches response X-Request-ID header with 500 reference_id body', function (): void {
    $response = $this->get('/api/v1/test-request-id/server-error');

    $response->assertStatus(500)
        ->assertHeader('X-Request-ID');

    $headerId = (string) $response->headers->get('X-Request-ID');
    $bodyId = $response->json('reference_id');

    expect($headerId)->toBe($bodyId);
});
