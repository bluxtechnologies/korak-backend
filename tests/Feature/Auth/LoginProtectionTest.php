<?php

use App\Models\Customer;
use App\Notifications\LockoutNotification;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config(['auth.device_verification_enabled' => false]);
});

it('locks account after 5 consecutive failed login attempts and dispatches LockoutNotification per Doc 08 §2', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'username' => 'lockout_user',
        'email' => 'lockout@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    // First 4 failed attempts: returns generic 401, no notification yet
    for ($i = 1; $i <= 4; $i++) {
        RateLimiter::clear('login');

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'lockout@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertExactJson([
                'message' => 'Invalid credentials.',
                'code' => 'invalid_credentials',
            ]);

        Notification::assertNothingSent();
    }

    // 5th failed attempt triggers account lockout and notification
    RateLimiter::clear('login');

    $fifthResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'lockout@example.com',
        'password' => 'WrongPassword!',
    ]);

    $fifthResponse->assertStatus(401)
        ->assertExactJson([
            'message' => 'Invalid credentials.',
            'code' => 'invalid_credentials',
        ]);

    Notification::assertSentTo(
        $customer,
        LockoutNotification::class,
        function (LockoutNotification $notification): bool {
            return $notification->durationMinutes === 15;
        }
    );

    expect(app(LoginLockoutService::class)->isLocked($customer))->toBeTrue();
});

it('rejects login with identical 401 when password is correct but account is locked per Doc 08 §2 & Doc 22 B6.4', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'locked_customer',
        'email' => 'locked@example.com',
        'password' => 'MySecretPass123!',
    ]);

    // Lock the account
    app(LoginLockoutService::class)->lock($customer);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'locked@example.com',
        'password' => 'MySecretPass123!',
    ]);

    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Invalid credentials.',
            'code' => 'invalid_credentials',
        ]);

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('returns identical 401 response for wrong password, unknown user, and locked account per Doc 08 §2 & Doc 22 B6.4', function (): void {
    $activeCustomer = Customer::factory()->create([
        'username' => 'active_user',
        'email' => 'active@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $lockedCustomer = Customer::factory()->create([
        'username' => 'locked_user',
        'email' => 'locked_user@example.com',
        'password' => 'CorrectPassword123!',
    ]);
    app(LoginLockoutService::class)->lock($lockedCustomer);

    // 1. Unknown user
    RateLimiter::clear('login');
    $unknownResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'doesnotexist@example.com',
        'password' => 'AnyPassword123!',
    ]);

    // 2. Active user, wrong password
    RateLimiter::clear('login');
    $wrongPassResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'active@example.com',
        'password' => 'WrongPassword123!',
    ]);

    // 3. Locked user, any password
    RateLimiter::clear('login');
    $lockedResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'locked_user@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    // All three responses must be identical
    $unknownResponse->assertStatus(401)->assertExactJson([
        'message' => 'Invalid credentials.',
        'code' => 'invalid_credentials',
    ]);

    $wrongPassResponse->assertStatus(401)->assertExactJson([
        'message' => 'Invalid credentials.',
        'code' => 'invalid_credentials',
    ]);

    $lockedResponse->assertStatus(401)->assertExactJson([
        'message' => 'Invalid credentials.',
        'code' => 'invalid_credentials',
    ]);
});

it('resets failed attempts upon successful login before reaching lockout threshold', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'recovering_user',
        'email' => 'recover@example.com',
        'password' => 'GoodPassword123!',
    ]);

    $service = app(LoginLockoutService::class);

    // 3 failed attempts
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::clear('login');
        $this->postJson('/api/v1/auth/login', [
            'login' => 'recover@example.com',
            'password' => 'WrongPassword!',
        ])->assertStatus(401);
    }

    expect($service->failedAttempts($customer))->toBe(3);

    // 4th attempt: successful login
    RateLimiter::clear('login');
    $success = $this->postJson('/api/v1/auth/login', [
        'login' => 'recover@example.com',
        'password' => 'GoodPassword123!',
    ]);

    $success->assertOk();

    // Failed attempts counter was reset to 0
    expect($service->failedAttempts($customer))->toBe(0)
        ->and($service->isLocked($customer))->toBeFalse();
});

it('allows login after 15-minute lockout period expires per Doc 08 §2', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'expiring_lock',
        'email' => 'expire_lock@example.com',
        'password' => 'MyPassword123!',
    ]);

    app(LoginLockoutService::class)->lock($customer);
    expect(app(LoginLockoutService::class)->isLocked($customer))->toBeTrue();

    // Fast-forward past 15-minute lockout window
    $this->travel(16)->minutes();

    expect(app(LoginLockoutService::class)->isLocked($customer))->toBeFalse();

    RateLimiter::clear('login');
    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'expire_lock@example.com',
        'password' => 'MyPassword123!',
    ]);

    $response->assertOk();
    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('throttles login attempts per identifier and IP per Doc 08 §2', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'target_ident_1',
            'password' => 'bad_pass',
        ])->assertStatus(401);
    }

    // 6th attempt for target_ident_1 must be throttled with 429
    $throttled = $this->postJson('/api/v1/auth/login', [
        'login' => 'target_ident_1',
        'password' => 'bad_pass',
    ]);

    $throttled->assertStatus(429)
        ->assertJson([
            'code' => 'too_many_requests',
        ]);

    // Distinct identifier from same IP has its own rate limiter bucket
    $otherIdentResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'different_ident_2',
        'password' => 'bad_pass',
    ]);

    // Not throttled: returns 401
    $otherIdentResponse->assertStatus(401);
});

it('clears lockout when password is reset via reset-password endpoint', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'locked_reset@example.com',
        'password' => 'OldPassword123!',
    ]);

    $service = app(LoginLockoutService::class);
    $service->lock($customer);
    expect($service->isLocked($customer))->toBeTrue();

    $token = Password::broker('customers')->createToken($customer);

    $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'locked_reset@example.com',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $resetResponse->assertOk();

    // Lockout was cleared by password reset
    expect($service->isLocked($customer))->toBeFalse();

    // Customer can immediately log in with new password
    RateLimiter::clear('login');
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'locked_reset@example.com',
        'password' => 'BrandNewPassword123!',
    ]);

    $loginResponse->assertOk();
    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('confirms LockoutNotification implements ShouldQueue per Doc 11', function (): void {
    $implements = class_implements(LockoutNotification::class);
    expect($implements)->toHaveKey(ShouldQueue::class);
});
