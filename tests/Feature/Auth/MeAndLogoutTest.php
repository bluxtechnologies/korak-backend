<?php

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['auth.device_verification_enabled' => false]);
});

it('returns authenticated customer profile on auth/me per Doc 04 §3 and Doc 06', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'profile_user',
        'email' => 'profile@example.com',
    ]);

    $response = $this->actingAs($customer, 'customer')->getJson('/api/v1/auth/me');

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
                'username' => 'profile_user',
                'email' => 'profile@example.com',
            ],
        ]);

    expect(Str::isUlid($response->json('data.id')))->toBeTrue()
        ->and($response->json('data'))->not->toHaveKey('password')
        ->and($response->json('data'))->not->toHaveKey('remember_token')
        ->and($response->headers->has('X-Request-ID'))->toBeTrue();
});

it('rejects unauthenticated requests on auth/me with standard 401', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);
});

it('rejects admin sessions on customer auth/me route per Doc 04 and Doc 13', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->getJson('/api/v1/auth/me');

    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);
});

it('logs out customer and invalidates session per Doc 08 §3', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    expect(Auth::guard('customer')->check())->toBeFalse();

    // Subsequent access to protected endpoint returns 401
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('rejects unauthenticated logout requests with 401', function (): void {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);
});

it('completes the full authentication lifecycle (register -> login -> me -> logout -> me)', function (): void {
    // 1. Register customer
    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'username' => 'lifecycle_user',
        'email' => 'lifecycle@example.com',
        'password' => 'LifecyclePassword123!',
        'password_confirmation' => 'LifecyclePassword123!',
    ]);
    $registerResponse->assertStatus(201);
    $ulid = $registerResponse->json('data.id');

    // 2. Login customer
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'lifecycle@example.com',
        'password' => 'LifecyclePassword123!',
    ]);
    $loginResponse->assertOk()
        ->assertJson([
            'data' => [
                'id' => $ulid,
                'username' => 'lifecycle_user',
            ],
        ]);

    // 3. Inspect profile via /auth/me
    $meResponse = $this->getJson('/api/v1/auth/me');
    $meResponse->assertOk()
        ->assertJson([
            'data' => [
                'id' => $ulid,
                'username' => 'lifecycle_user',
                'email' => 'lifecycle@example.com',
            ],
        ]);

    // 4. Logout
    $logoutResponse = $this->postJson('/api/v1/auth/logout');
    $logoutResponse->assertNoContent();

    // 5. Verify /auth/me is now unauthenticated
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJson([
            'code' => 'unauthenticated',
        ]);
});
