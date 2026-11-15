<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Config::set('cors.allowed_origins', ['http://localhost:3000', 'http://localhost:8070']);
    Config::set('cors.supports_credentials', true);
    Config::set('cors.exposed_headers', ['X-Request-ID']);
    Config::set('sanctum.stateful', ['localhost:3000', 'localhost:8070']);

    Route::middleware('api')->get('/api/test-cors', function () {
        return response()->json(['message' => 'cors-ok']);
    });
});

it('responds to CORS preflight OPTIONS request for allowed origin', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'Content-Type, X-Request-ID, X-XSRF-TOKEN',
    ])->options('/api/test-cors');

    $response->assertStatus(204);
    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('*');
});

it('rejects CORS preflight request for disallowed origin', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'http://evil.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/test-cors');

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://evil.com');
});

it('includes CORS headers and credentials on actual GET request from allowed origin', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
    ])->getJson('/api/test-cors');

    $response->assertOk();
    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    $response->assertHeader('Access-Control-Expose-Headers', 'X-Request-ID');
    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('*');
});

it('serves sanctum csrf-cookie endpoint with credentials and sets XSRF-TOKEN cookie', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
    ])->get('/sanctum/csrf-cookie');

    $response->assertNoContent();
    $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    $response->assertCookie('XSRF-TOKEN');
});

it('has session cookie security settings matching Doc 04 requirements', function (): void {
    expect(config('session.http_only'))->toBeTrue();
    expect(config('session.same_site'))->toBe('lax');
    expect(config('cors.supports_credentials'))->toBeTrue();
    expect(config('cors.allowed_origins'))->not->toContain('*');
});
