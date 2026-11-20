<?php

use App\Enums\AuditEventName;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Notifications\DeviceVerificationOtpNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    RateLimiter::clear('otp');
    config(['auth.device_verification_enabled' => true]);
});

it('records auth.registered event upon successful customer registration per Doc 12 §2', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'audited_user',
        'email' => 'audited@example.com',
        'phone_number' => '+2348011223344',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(201);

    $customer = Customer::where('email', 'audited@example.com')->first();
    expect($customer)->not->toBeNull();

    $event = AuditEvent::where('event', AuditEventName::AUTH_REGISTERED)->first();
    expect($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->subject_id)->toBe($customer->id)
        ->and($event->metadata['username'])->toBe('audited_user')
        ->and($event->metadata['email'])->toBe('audited@example.com')
        ->and($event->metadata)->not->toHaveKey('password');
});

it('records auth.login_success and auth.login_failed events per Doc 12 §2', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'login_audit_user',
        'email' => 'login_audit@example.com',
        'password' => 'CorrectPass123!',
    ]);

    // 1. Unknown user failed login
    $this->postJson('/api/v1/auth/login', [
        'login' => 'unknown_person@example.com',
        'password' => 'AnyPassword123!',
    ])->assertStatus(401);

    $failedUnknown = AuditEvent::where('event', AuditEventName::AUTH_LOGIN_FAILED)->first();
    expect($failedUnknown)->not->toBeNull()
        ->and($failedUnknown->metadata['identifier'])->toBe('unknown_person@example.com');

    // 2. Existing user wrong password failed login
    RateLimiter::clear('login');
    $this->postJson('/api/v1/auth/login', [
        'login' => 'login_audit@example.com',
        'password' => 'WrongPassword123!',
    ])->assertStatus(401);

    $failedPass = AuditEvent::where('event', AuditEventName::AUTH_LOGIN_FAILED)
        ->where('actor_id', $customer->id)
        ->first();
    expect($failedPass)->not->toBeNull()
        ->and($failedPass->metadata['reason'])->toBe('invalid_password');

    // 3. Successful login with trusted device
    $rawToken = app(DeviceVerificationService::class)->rememberDevice($customer, 'Work Laptop');

    RateLimiter::clear('login');
    $this->withHeader('X-Device-Token', $rawToken)->postJson('/api/v1/auth/login', [
        'login' => 'login_audit@example.com',
        'password' => 'CorrectPass123!',
    ])->assertOk();

    $successEvent = AuditEvent::where('event', AuditEventName::AUTH_LOGIN_SUCCESS)->first();
    expect($successEvent)->not->toBeNull()
        ->and($successEvent->actor_id)->toBe($customer->id);
});

it('records auth.lockout event when customer account is locked after 5 failures per Doc 12 §2', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'lockout_audit@example.com',
        'password' => 'Password123!',
    ]);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::clear('login');
        $this->postJson('/api/v1/auth/login', [
            'login' => 'lockout_audit@example.com',
            'password' => 'WrongPass!',
        ]);
    }

    $lockoutEvent = AuditEvent::where('event', AuditEventName::AUTH_LOCKOUT)->first();
    expect($lockoutEvent)->not->toBeNull()
        ->and($lockoutEvent->actor_id)->toBe($customer->id);
});

it('records device events during unrecognised device challenge and verification per Doc 12 §2', function (): void {
    Notification::fake();

    $customer = Customer::factory()->withDeviceVerification()->create([
        'email' => 'device_audit@example.com',
        'password' => 'Password123!',
    ]);

    // 1. Login from unrecognised device -> challenge issued
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'device_audit@example.com',
        'password' => 'Password123!',
        'device_name' => 'Firefox on Linux',
    ]);
    $loginResponse->assertOk();

    $challengeToken = $loginResponse->json('data.challenge_token');

    $challengeEvent = AuditEvent::where('event', AuditEventName::DEVICE_CHALLENGE_ISSUED)->first();
    expect($challengeEvent)->not->toBeNull()
        ->and($challengeEvent->actor_id)->toBe($customer->id)
        ->and($challengeEvent->metadata['device_name'])->toBe('Firefox on Linux');

    // 2. Failed OTP verification
    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => '000000',
    ])->assertStatus(400);

    $verifyFailedEvent = AuditEvent::where('event', AuditEventName::DEVICE_VERIFICATION_FAILED)->first();
    expect($verifyFailedEvent)->not->toBeNull()
        ->and($verifyFailedEvent->metadata['reason'])->toBe('invalid_verification_code');

    // 3. Successful OTP verification with remember device
    $sentCode = null;
    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification) use (&$sentCode): bool {
            $sentCode = $notification->code;

            return true;
        }
    );

    RateLimiter::clear('otp');
    $this->postJson('/api/v1/auth/verify-device', [
        'challenge_token' => $challengeToken,
        'code' => $sentCode,
        'remember_device' => true,
        'device_name' => 'Firefox on Linux',
    ])->assertOk();

    $verifySuccessEvent = AuditEvent::where('event', AuditEventName::DEVICE_VERIFICATION_SUCCESS)->first();
    expect($verifySuccessEvent)->not->toBeNull()
        ->and($verifySuccessEvent->actor_id)->toBe($customer->id);

    $deviceTrustedEvent = AuditEvent::where('event', AuditEventName::DEVICE_TRUSTED)->first();
    expect($deviceTrustedEvent)->not->toBeNull()
        ->and($deviceTrustedEvent->actor_id)->toBe($customer->id)
        ->and($deviceTrustedEvent->metadata['device_name'])->toBe('Firefox on Linux');
});

it('records auth.logout event upon customer sign out per Doc 12 §2', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $logoutEvent = AuditEvent::where('event', AuditEventName::AUTH_LOGOUT)->first();
    expect($logoutEvent)->not->toBeNull()
        ->and($logoutEvent->actor_id)->toBe($customer->id);
});

it('records auth.password_reset_requested and auth.password_reset_completed events per Doc 12 §2', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'pw_audit@example.com',
        'password' => 'OldPassword123!',
    ]);

    // 1. Forgot password
    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'pw_audit@example.com',
    ])->assertOk();

    $requestedEvent = AuditEvent::where('event', AuditEventName::AUTH_PASSWORD_RESET_REQUESTED)->first();
    expect($requestedEvent)->not->toBeNull()
        ->and($requestedEvent->actor_id)->toBe($customer->id)
        ->and($requestedEvent->metadata['email'])->toBe('pw_audit@example.com');

    // 2. Reset password
    $token = Password::broker('customers')->createToken($customer);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'pw_audit@example.com',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ])->assertOk();

    $completedEvent = AuditEvent::where('event', AuditEventName::AUTH_PASSWORD_RESET_COMPLETED)->first();
    expect($completedEvent)->not->toBeNull()
        ->and($completedEvent->actor_id)->toBe($customer->id);
});

it('records device.revoked and device.revoked_all events per Doc 12 §2', function (): void {
    $customer = Customer::factory()->create();
    $service = app(DeviceVerificationService::class);

    $service->rememberDevice($customer, 'Device A');
    $service->rememberDevice($customer, 'Device B');

    $deviceA = $customer->trustedDevices()->first();
    expect($deviceA)->not->toBeNull();

    // 1. Revoke single device
    $this->actingAs($customer, 'customer')
        ->deleteJson("/api/v1/auth/devices/{$deviceA->ulid}")
        ->assertOk();

    $revokedEvent = AuditEvent::where('event', AuditEventName::DEVICE_REVOKED)->first();
    expect($revokedEvent)->not->toBeNull()
        ->and($revokedEvent->actor_id)->toBe($customer->id)
        ->and($revokedEvent->metadata['device_ulid'])->toBe($deviceA->ulid);

    // 2. Revoke all devices
    $this->actingAs($customer, 'customer')
        ->deleteJson('/api/v1/auth/devices')
        ->assertOk();

    $revokedAllEvent = AuditEvent::where('event', AuditEventName::DEVICE_REVOKED_ALL)->first();
    expect($revokedAllEvent)->not->toBeNull()
        ->and($revokedAllEvent->actor_id)->toBe($customer->id)
        ->and($revokedAllEvent->metadata['revoked_count'])->toBe(1);
});

it('guarantees that metadata never holds raw passwords, tokens, or OTP codes per Doc 12 §4 & Doc 08 §12', function (): void {
    $logger = app(AuditLogger::class);

    $event = $logger->log(
        event: AuditEventName::AUTH_REGISTERED,
        metadata: [
            'password' => 'Password123!',
            'token' => 'plain-token',
            'device_token' => 'secret_device_token',
            'safe_param' => 'valid',
        ]
    );

    expect($event->metadata)->toBe([
        'password' => '[REDACTED]',
        'token' => '[REDACTED]',
        'device_token' => '[REDACTED]',
        'safe_param' => 'valid',
    ]);
});
