<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('returns healthy status when database is operational', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'status',
                'services' => [
                    'database',
                ],
                'timestamp',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'status' => 'ok',
                'services' => [
                    'database' => 'ok',
                ],
            ],
        ]);

    expect($response->headers->has('X-Request-ID'))->toBeTrue();
});

it('returns degraded status 503 and leaks nothing when database fails', function (): void {
    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new PDOException('SQLSTATE[HY000] [2002] Connection refused: sensitive-db-host:3306'));

    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(503)
        ->assertJson([
            'data' => [
                'status' => 'degraded',
                'services' => [
                    'database' => 'error',
                ],
            ],
        ]);

    $content = $response->getContent();
    expect($content)->not->toContain('sensitive-db-host')
        ->and($content)->not->toContain('SQLSTATE')
        ->and($content)->not->toContain('Connection refused');
});

it('throttles requests when exceeding baseline api rate limit and returns standard envelope', function (): void {
    RateLimiter::for('api-test', function (Request $request): Limit {
        return Limit::perMinute(3)->by($request->ip());
    });

    Route::middleware(['api', 'throttle:api-test'])->get('/api/v1/test-throttle', function () {
        return ApiResponse::success(['status' => 'ok']);
    });

    // Requests 1, 2, 3 should succeed
    for ($i = 0; $i < 3; $i++) {
        $this->getJson('/api/v1/test-throttle')->assertOk();
    }

    // Request 4 must be throttled with 429
    $response = $this->getJson('/api/v1/test-throttle');

    $response->assertStatus(429)
        ->assertExactJson([
            'message' => 'Too many requests. Please try again later.',
            'code' => 'too_many_requests',
        ]);

    expect($response->headers->has('Retry-After'))->toBeTrue()
        ->and($response->headers->has('X-Request-ID'))->toBeTrue();

    // Ensure no framework or rule internals leaked
    $content = $response->getContent();
    expect($content)->not->toContain('api-test')
        ->and($content)->not->toContain('ThrottleRequestsException');
});

it('registers all required named rate limiters per Doc 06 and Doc 08', function (): void {
    $request = Request::create('/api/v1/test', 'POST', [
        'email' => 'user@example.com',
    ]);

    // api baseline (60)
    $apiLimiter = RateLimiter::limiter('api');
    expect($apiLimiter)->not->toBeNull();
    /** @var Limit $apiLimit */
    $apiLimit = $apiLimiter($request);
    expect($apiLimit->maxAttempts)->toBe(60);

    // login (5)
    $loginLimiter = RateLimiter::limiter('login');
    expect($loginLimiter)->not->toBeNull();
    /** @var Limit $loginLimit */
    $loginLimit = $loginLimiter($request);
    expect($loginLimit->maxAttempts)->toBe(5);

    // otp (3)
    $otpLimiter = RateLimiter::limiter('otp');
    expect($otpLimiter)->not->toBeNull();
    /** @var Limit $otpLimit */
    $otpLimit = $otpLimiter($request);
    expect($otpLimit->maxAttempts)->toBe(3);

    // transactions (10)
    $txLimiter = RateLimiter::limiter('transactions');
    expect($txLimiter)->not->toBeNull();
    /** @var Limit $txLimit */
    $txLimit = $txLimiter($request);
    expect($txLimit->maxAttempts)->toBe(10);

    // health (120)
    $healthLimiter = RateLimiter::limiter('health');
    expect($healthLimiter)->not->toBeNull();
    /** @var Limit $healthLimit */
    $healthLimit = $healthLimiter($request);
    expect($healthLimit->maxAttempts)->toBe(120);
});
