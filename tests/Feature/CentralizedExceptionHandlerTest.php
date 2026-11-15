<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function (): void {
    Route::prefix('api/v1/test-exceptions')->group(function (): void {
        Route::get('/validation', function (): void {
            throw ValidationException::withMessages([
                'username' => ['The username has already been taken.'],
            ]);
        });

        Route::get('/unauthenticated', function (): void {
            throw new AuthenticationException;
        });

        Route::get('/forbidden-authorization', function (): void {
            throw new AuthorizationException;
        });

        Route::get('/forbidden-access-denied', function (): void {
            throw new AccessDeniedHttpException;
        });

        Route::get('/model-not-found', function (): void {
            throw (new ModelNotFoundException)->setModel('App\\Models\\Customer', [999]);
        });

        Route::get('/throttle', function (): void {
            throw new ThrottleRequestsException;
        });

        Route::get('/abort-conflict', function (): void {
            abort(409, 'Resource conflict.');
        });

        Route::get('/abort-locked', function (): void {
            abort(423, 'Account is temporarily locked.');
        });

        Route::get('/server-error', function (): void {
            throw new RuntimeException('SELECT * FROM secret_passwords WHERE id = 1');
        });
    });
});

it('formats validation exceptions with 422 and field errors', function (): void {
    $response = $this->getJson('/api/v1/test-exceptions/validation');

    $response->assertStatus(422)
        ->assertExactJson([
            'message' => 'The username has already been taken.',
            'code' => 'validation_failed',
            'errors' => [
                'username' => ['The username has already been taken.'],
            ],
        ]);
});

it('formats authentication exceptions with 401 without errors key', function (): void {
    $response = $this->get('/api/v1/test-exceptions/unauthenticated');

    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);
});

it('formats authorization exceptions with 403', function (): void {
    $response = $this->get('/api/v1/test-exceptions/forbidden-authorization');

    $response->assertStatus(403)
        ->assertExactJson([
            'message' => 'This action is unauthorized.',
            'code' => 'forbidden',
        ]);
});

it('formats access denied http exceptions with 403', function (): void {
    $response = $this->get('/api/v1/test-exceptions/forbidden-access-denied');

    $response->assertStatus(403)
        ->assertExactJson([
            'message' => 'This action is unauthorized.',
            'code' => 'forbidden',
        ]);
});

it('formats model not found exceptions with 404 without exposing internal model or ID', function (): void {
    $response = $this->get('/api/v1/test-exceptions/model-not-found');

    $response->assertStatus(404)
        ->assertExactJson([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ]);

    expect($response->getContent())->not->toContain('Customer')
        ->and($response->getContent())->not->toContain('999');
});

it('formats not found routes with 404', function (): void {
    $response = $this->get('/api/v1/does-not-exist');

    $response->assertStatus(404)
        ->assertExactJson([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ]);
});

it('formats method not allowed with 405', function (): void {
    $response = $this->post('/api/v1/test-exceptions/unauthenticated');

    $response->assertStatus(405)
        ->assertExactJson([
            'message' => 'Method not allowed.',
            'code' => 'method_not_allowed',
        ]);
});

it('formats throttled requests with 429', function (): void {
    $response = $this->get('/api/v1/test-exceptions/throttle');

    $response->assertStatus(429)
        ->assertExactJson([
            'message' => 'Too many requests. Please try again later.',
            'code' => 'too_many_requests',
        ]);
});

it('formats http abort exceptions with corresponding status and code', function (): void {
    $this->get('/api/v1/test-exceptions/abort-conflict')
        ->assertStatus(409)
        ->assertExactJson([
            'message' => 'Resource conflict.',
            'code' => 'conflict',
        ]);

    $this->get('/api/v1/test-exceptions/abort-locked')
        ->assertStatus(423)
        ->assertExactJson([
            'message' => 'Account is temporarily locked.',
            'code' => 'locked',
        ]);
});

it('formats 500 server errors with a reference ID and leaks no SQL or internal details', function (): void {
    $response = $this->get('/api/v1/test-exceptions/server-error');

    $response->assertStatus(500);

    $json = $response->getData(true);
    expect($json)->toHaveKeys(['message', 'code', 'reference_id'])
        ->and($json['message'])->toBe('An unexpected server error occurred.')
        ->and($json['code'])->toBe('server_error')
        ->and($json['reference_id'])->toBeString()->not->toBeEmpty();

    $content = $response->getContent();
    expect($content)->not->toContain('SELECT * FROM secret_passwords')
        ->and($content)->not->toContain('RuntimeException')
        ->and($content)->not->toContain('Stack trace')
        ->and($content)->not->toContain('.php');
});

it('uses incoming X-Request-ID as reference_id on server errors when available', function (): void {
    $response = $this->withHeader('X-Request-ID', 'custom-trace-id-123')
        ->get('/api/v1/test-exceptions/server-error');

    $response->assertStatus(500);

    $json = $response->getData(true);
    expect($json['reference_id'])->toBe('custom-trace-id-123');
});
