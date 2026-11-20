<?php

use App\Models\Customer;
use App\Models\CustomerDeviceChallenge;
use App\Models\CustomerTrustedDevice;
use App\Notifications\DeviceVerificationOtpNotification;
use App\Services\Auth\DeviceVerificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('login');
    RateLimiter::clear('otp');
    config(['auth.device_verification_enabled' => true]);
});

it('detects unrecognised device on login and requires OTP verification per Doc 08 §4 and Doc 22 B7.2', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'username' => 'device_user',
        'email' => 'device@example.com',
        'password' => 'Password123!',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'device@example.com',
        'password' => 'Password123!',
        'device_name' => 'Safari on iPhone',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'status',
                'challenge_token',
                'expires_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'status' => 'device_verification_required',
            ],
        ]);

    // Crucial: Customer is NOT logged in yet
    expect(Auth::guard('customer')->check())->toBeFalse();

    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification): bool {
            return $notification->deviceName === 'Safari on iPhone'
                && strlen($notification->code) === 6;
        }
    );
});

it('prevents unrecognised device from reaching protected endpoints until verified', function (): void {
    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'unverified@example.com',
        'password' => 'Password123!',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'login' => 'unverified@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    // Protected endpoint must reject with 401 unauthenticated
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJson([
            'code' => 'unauthenticated',
        ]);
});

it('verifies OTP code and authenticates customer session per Doc 22 B7.4', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'verify_success@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'verify_success@example.com',
        'password' => 'Password123!',
    ]);

    $challengeToken = $loginResponse->json('data.challenge_token');

    // Retrieve sent OTP
    $sentCode = null;
    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification) use (&$sentCode): bool {
            $sentCode = $notification->code;

            return true;
        }
    );

    $verifyResponse = $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => $sentCode,
        'remember_device' => false,
    ]);

    $verifyResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'email',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'email' => 'verify_success@example.com',
            ],
        ]);

    // Customer is now authenticated
    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->id())->toBe($customer->id);

    // Protected endpoints accessible
    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJson([
            'data' => [
                'email' => 'verify_success@example.com',
            ],
        ]);
});

it('remembers device when remember_device is true and bypasses OTP on next login', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'remember_me@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'remember_me@example.com',
        'password' => 'Password123!',
    ]);

    $challengeToken = $loginResponse->json('data.challenge_token');

    $sentCode = null;
    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification) use (&$sentCode): bool {
            $sentCode = $notification->code;

            return true;
        }
    );

    $verifyResponse = $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => $sentCode,
        'remember_device' => true,
        'device_name' => 'My MacBook Air',
    ]);

    $verifyResponse->assertOk()
        ->assertCookie('device_token');

    $deviceToken = $verifyResponse->json('meta.device_token');
    expect($deviceToken)->toBeString()->toHaveLength(64);

    // Device token is stored hashed in DB
    $trustedRecord = CustomerTrustedDevice::where('customer_id', $customer->id)->first();
    expect($trustedRecord)->not->toBeNull()
        ->and($trustedRecord->device_name)->toBe('My MacBook Air')
        ->and($trustedRecord->device_token_hash)->toBe(hash('sha256', $deviceToken));

    // Logout (Doc 06 §4 returns 204 No Content)
    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    expect(Auth::guard('customer')->check())->toBeFalse();

    // Subsequent login presenting X-Device-Token header directly succeeds without OTP challenge!
    Notification::fake(); // reset notifications

    $nextLogin = $this->withHeader('X-Device-Token', $deviceToken)->postJson('/api/v1/auth/login', [
        'login' => 'remember_me@example.com',
        'password' => 'Password123!',
    ]);

    $nextLogin->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'username', 'email'],
            'meta',
        ]);

    expect(Auth::guard('customer')->check())->toBeTrue();
    Notification::assertNothingSent();
});

it('rejects invalid OTP code and locks challenge after 3 attempts per Doc 08 §4', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'failed_otp@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'failed_otp@example.com',
        'password' => 'Password123!',
    ]);
    $challengeToken = $loginResponse->json('data.challenge_token');

    // Attempt 1: wrong code
    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => '999999',
    ])->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_verification_code',
            'message' => 'Invalid verification code.',
        ]);

    RateLimiter::clear('otp');

    // Attempt 2: wrong code
    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => '999998',
    ])->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_verification_code',
        ]);

    RateLimiter::clear('otp');

    // Attempt 3: 3rd failed attempt destroys challenge
    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => '999997',
    ])->assertStatus(400)
        ->assertJson([
            'code' => 'challenge_limit_exceeded',
            'message' => 'Too many failed verification attempts. Please log in again.',
        ]);

    expect(CustomerDeviceChallenge::where('challenge_token', $challengeToken)->exists())->toBeFalse();
});

it('rejects expired OTP challenge per Doc 08 §4', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'expired_otp@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'expired_otp@example.com',
        'password' => 'Password123!',
    ]);
    $challengeToken = $loginResponse->json('data.challenge_token');

    // Fast forward 11 minutes
    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => '123456',
    ])->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_or_expired_challenge',
        ]);

    Carbon::setTestNow();
});

it('throttles OTP verification attempts exceeding limit per Doc 08 §2', function (): void {
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/verify-device', [
            'challenge_token' => 'some_challenge_token',
            'code' => '123456',
        ])->assertStatus(400);
    }

    // 4th attempt throttled
    $throttled = $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => 'some_challenge_token',
        'code' => '123456',
    ]);

    $throttled->assertStatus(429)
        ->assertJson([
            'code' => 'too_many_requests',
        ]);
});

it('lists trusted devices for authenticated customer without leaking token hashes per Doc 06 & Doc 22 B7.5', function (): void {
    $customer = Customer::factory()->create();
    $service = app(DeviceVerificationService::class);

    $service->rememberDevice($customer, 'Home PC', '192.168.1.10');
    $service->rememberDevice($customer, 'Work Laptop', '10.0.0.50');

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/auth/devices');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'device_name',
                    'ip_address',
                    'last_used_at',
                    'created_at',
                ],
            ],
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(2);

    // Verify token hash is NOT leaked
    foreach ($response->json('data') as $device) {
        expect($device)->not->toHaveKey('device_token_hash')
            ->and($device)->not->toHaveKey('token');
    }
});

it('revokes a specific trusted device and denies cross-customer revocation per Doc 08 §4 & Doc 22 B7.5', function (): void {
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $service = app(DeviceVerificationService::class);

    $tokenA = $service->rememberDevice($customerA, 'Device A');
    $tokenB = $service->rememberDevice($customerB, 'Device B');

    $deviceB = CustomerTrustedDevice::where('customer_id', $customerB->id)->first();
    expect($deviceB)->not->toBeNull();

    // Customer A attempts to revoke Customer B's device -> must fail with 404
    $this->actingAs($customerA, 'customer')
        ->deleteJson("/api/v1/auth/devices/{$deviceB->ulid}")
        ->assertStatus(404)
        ->assertJson([
            'code' => 'device_not_found',
        ]);

    expect(CustomerTrustedDevice::where('id', $deviceB->id)->exists())->toBeTrue();

    // Customer B revokes their own device -> succeeds
    $this->actingAs($customerB, 'customer')
        ->deleteJson("/api/v1/auth/devices/{$deviceB->ulid}")
        ->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Device revoked successfully.',
            ],
        ]);

    expect(CustomerTrustedDevice::where('id', $deviceB->id)->exists())->toBeFalse();
});

it('revokes all trusted devices for authenticated customer per Doc 22 B7.5', function (): void {
    $customer = Customer::factory()->create();
    $service = app(DeviceVerificationService::class);

    $service->rememberDevice($customer, 'Device 1');
    $service->rememberDevice($customer, 'Device 2');

    expect(CustomerTrustedDevice::where('customer_id', $customer->id)->count())->toBe(2);

    $response = $this->actingAs($customer, 'customer')
        ->deleteJson('/api/v1/auth/devices');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'All devices revoked successfully.',
                'revoked_count' => 2,
            ],
        ]);

    expect(CustomerTrustedDevice::where('customer_id', $customer->id)->count())->toBe(0);
});

it('confirms DeviceVerificationOtpNotification implements ShouldQueue per Doc 11', function (): void {
    $implements = class_implements(DeviceVerificationOtpNotification::class);
    expect($implements)->toHaveKey(ShouldQueue::class);
});

it('does not require device verification for newly registered customers by default', function (): void {
    Notification::fake();

    // 1. Register a new user
    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'First Time User',
        'email' => 'firsttime@example.com',
        'phone_number' => '+2348099887766',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $registerResponse->assertStatus(201);
    expect($registerResponse->json('data.device_verification_enabled'))->toBeFalse();

    // 2. Immediate login succeeds directly without device challenge
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'identifier' => 'firsttime@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse->assertOk()
        ->assertJson([
            'data' => [
                'email' => 'firsttime@example.com',
                'device_verification_enabled' => false,
            ],
        ]);

    expect(Auth::guard('customer')->check())->toBeTrue();
    Notification::assertNothingSent();
});

it('allows customer to toggle device verification in profile settings', function (): void {
    $customer = Customer::factory()->create([
        'device_verification_enabled' => false,
    ]);

    // 1. Turn on device verification in profile settings
    $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'device_verification_enabled' => true,
        ])
        ->assertOk()
        ->assertJson([
            'data' => [
                'device_verification_enabled' => true,
            ],
        ]);

    expect($customer->fresh()->device_verification_enabled)->toBeTrue();

    // 2. Turn off device verification in profile settings
    $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'device_verification_enabled' => false,
        ])
        ->assertOk()
        ->assertJson([
            'data' => [
                'device_verification_enabled' => false,
            ],
        ]);

    expect($customer->fresh()->device_verification_enabled)->toBeFalse();
});

it('resends device verification OTP code successfully with challenge_token', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'resend_test@example.com',
        'password' => 'Password123!',
        'device_verification_enabled' => true,
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'resend_test@example.com',
        'password' => 'Password123!',
        'device_name' => 'Firefox on Linux',
    ])->assertOk();

    $challengeToken = $loginResponse->json('data.challenge_token');
    expect($challengeToken)->not->toBeNull();

    $challenge = CustomerDeviceChallenge::where('challenge_token', $challengeToken)->first();
    $oldOtpHash = $challenge->otp_hash;

    // Resend OTP via POST /api/v1/auth/verify-device/resend
    $resendResponse = $this->postJson('/api/v1/auth/verify-device/resend', [
        'challenge_token' => $challengeToken,
    ]);

    $resendResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                'status',
                'requires_device_verification',
                'message',
                'challenge_token',
                'expires_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'status' => 'device_verification_required',
                'requires_device_verification' => true,
                'message' => 'Verification code resent successfully.',
                'challenge_token' => $challengeToken,
            ],
        ]);

    // Challenge OTP hash must be updated
    $freshChallenge = $challenge->fresh();
    expect($freshChallenge->otp_hash)->not->toBe($oldOtpHash);
    expect($freshChallenge->attempts)->toBe(0);

    // Notification must be dispatched
    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification): bool {
            return strlen($notification->code) === 6;
        }
    );
});

it('resends device verification OTP code using alias routes', function (string $route): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'alias_test@example.com',
        'password' => 'Password123!',
        'device_verification_enabled' => true,
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'alias_test@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $challengeToken = $loginResponse->json('data.challenge_token');

    $response = $this->postJson($route, [
        'challenge_token' => $challengeToken,
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'requires_device_verification' => true,
                'message' => 'Verification code resent successfully.',
            ],
        ]);
})->with([
    '/api/v1/auth/verify-device/resend',
    '/api/v1/auth/resend-device-code',
    '/api/v1/auth/resend-otp',
]);

it('resends device verification OTP code using customer identifier if challenge token is missing or expired', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'ident_resend@example.com',
        'phone_number' => '+2348011223344',
        'password' => 'Password123!',
        'device_verification_enabled' => true,
    ]);

    $resendResponse = $this->postJson('/api/v1/auth/verify-device/resend', [
        'email' => 'ident_resend@example.com',
    ]);

    $resendResponse->assertOk()
        ->assertJsonStructure([
            'data' => [
                'challenge_token',
                'expires_at',
            ],
        ]);

    expect(CustomerDeviceChallenge::where('customer_id', $customer->id)->exists())->toBeTrue();
    Notification::assertSentTo($customer, DeviceVerificationOtpNotification::class);
});

it('rejects resend if challenge token is invalid and no valid customer identifier provided', function (): void {
    $response = $this->postJson('/api/v1/auth/verify-device/resend', [
        'challenge_token' => 'non_existent_token_12345678901234567890',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_or_expired_challenge',
        ]);
});

it('rejects resend if challenge token is expired and cleans up DB', function (): void {
    $customer = Customer::factory()->create();

    $challenge = CustomerDeviceChallenge::create([
        'customer_id' => $customer->id,
        'challenge_token' => 'expired_challenge_token_12345',
        'otp_hash' => hash('sha256', '123456'),
        'attempts' => 0,
        'expires_at' => Carbon::now()->subMinutes(5),
    ]);

    $response = $this->postJson('/api/v1/auth/verify-device/resend', [
        'challenge_token' => 'expired_challenge_token_12345',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_or_expired_challenge',
        ]);

    expect(CustomerDeviceChallenge::where('id', $challenge->id)->exists())->toBeFalse();
});

it('throttles excessive device verification resend attempts', function (): void {
    RateLimiter::clear('otp');

    $customer = Customer::factory()->create([
        'email' => 'throttle_resend@example.com',
        'password' => 'Password123!',
        'device_verification_enabled' => true,
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'throttle_resend@example.com',
        'password' => 'Password123!',
    ])->assertOk();

    $challengeToken = $loginResponse->json('data.challenge_token');

    // 3 allowed per minute
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/verify-device/resend', [
            'challenge_token' => $challengeToken,
        ])->assertOk();
    }

    // 4th is throttled
    $this->postJson('/api/v1/auth/verify-device/resend', [
        'challenge_token' => $challengeToken,
    ])->assertStatus(429)
        ->assertJson([
            'code' => 'too_many_requests',
        ]);
});
