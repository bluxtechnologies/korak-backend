<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('login');
    config(['auth.device_verification_enabled' => false]);
});

it('logs in customer using email and returns customer resource per Doc 04 and Doc 06', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'SecretPassword123!',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'john@example.com',
        'password' => 'SecretPassword123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'email',
                'email_verified_at',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'id' => $customer->ulid,
                'username' => 'johndoe',
                'email' => 'john@example.com',
            ],
        ]);

    expect(Str::isUlid($response->json('data.id')))->toBeTrue()
        ->and($response->json('data'))->not->toHaveKey('password')
        ->and(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);
});

it('logs in customer using username', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'jane_doe',
        'email' => 'jane@example.com',
        'password' => 'SecretPassword123!',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'jane_doe',
        'password' => 'SecretPassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $customer->ulid,
                'username' => 'jane_doe',
            ],
        ]);

    expect(Auth::guard('customer')->check())->toBeTrue();
});

it('logs in customer using phone number in login field', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'phone_user',
        'email' => 'phone_user@example.com',
        'phone_number' => '+2348012345678',
        'password' => 'SecretPassword123!',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '+2348012345678',
        'password' => 'SecretPassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $customer->ulid,
                'email' => 'phone_user@example.com',
                'phone_number' => '+2348012345678',
            ],
        ]);

    expect(Auth::guard('customer')->check())->toBeTrue();
});

it('accepts explicit email, username, or phone_number field in login payload', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'custom_user',
        'email' => 'custom@example.com',
        'phone_number' => '+2348099887766',
        'password' => 'SecretPassword123!',
    ]);

    // Test with explicit 'email' key
    $emailResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'custom@example.com',
        'password' => 'SecretPassword123!',
    ]);
    $emailResponse->assertOk();

    // Test with explicit 'username' key
    $usernameResponse = $this->postJson('/api/v1/auth/login', [
        'username' => 'custom_user',
        'password' => 'SecretPassword123!',
    ]);
    $usernameResponse->assertOk();

    // Test with explicit 'phone_number' key
    $phoneResponse = $this->postJson('/api/v1/auth/login', [
        'phone_number' => '+2348099887766',
        'password' => 'SecretPassword123!',
    ]);
    $phoneResponse->assertOk();
});

it('returns identical generic 401 error for wrong password or non-existent account per Doc 08 §1', function (): void {
    Customer::factory()->create([
        'username' => 'real_user',
        'email' => 'real@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    // 1. Existing user with wrong password
    $wrongPassResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'real@example.com',
        'password' => 'WrongPassword!',
    ]);

    $wrongPassResponse->assertStatus(401)
        ->assertExactJson([
            'message' => 'Invalid credentials.',
            'code' => 'invalid_credentials',
        ]);

    // 2. Non-existent user
    $unknownUserResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'nonexistent@example.com',
        'password' => 'AnyPassword123!',
    ]);

    $unknownUserResponse->assertStatus(401)
        ->assertExactJson([
            'message' => 'Invalid credentials.',
            'code' => 'invalid_credentials',
        ]);

    // Ensure session was NOT authenticated
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('fails validation when required fields are missing in login request', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['login', 'password'])
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('authenticates stateful SPA login request from allowed origin and regenerates session cookie per Doc 04', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'spa_user',
        'email' => 'spa@example.com',
        'password' => 'SecretPassword123!',
    ]);

    // 1. Fetch CSRF cookie from stateful origin
    $csrfResponse = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
    ])->get('/sanctum/csrf-cookie');
    $csrfResponse->assertNoContent();
    $csrfResponse->assertCookie('XSRF-TOKEN');

    // 2. Perform login from stateful origin
    $loginResponse = $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Referer' => 'http://localhost:3000/',
    ])->postJson('/api/v1/auth/login', [
        'login' => 'spa@example.com',
        'password' => 'SecretPassword123!',
    ]);

    $loginResponse->assertOk()
        ->assertJson([
            'data' => [
                'username' => 'spa_user',
                'email' => 'spa@example.com',
            ],
        ]);

    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->user()?->username)->toBe('spa_user');
});

it('throttles login attempts after 5 failures per Doc 08 §2', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'target_user',
            'password' => 'wrong_password',
        ])->assertStatus(401);
    }

    // 6th attempt must be throttled with 429
    $throttledResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'target_user',
        'password' => 'wrong_password',
    ]);

    $throttledResponse->assertStatus(429)
        ->assertExactJson([
            'message' => 'Too many requests. Please try again later.',
            'code' => 'too_many_requests',
        ]);

    expect($throttledResponse->headers->has('Retry-After'))->toBeTrue();
});
