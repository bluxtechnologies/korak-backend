<?php

use App\Models\Customer;
use App\Models\CustomerDeviceChallenge;
use App\Models\CustomerTrustedDevice;
use App\Notifications\DeviceVerificationOtpNotification;
use App\Services\Auth\DeviceVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('detects unrecognised device when no token or invalid token is provided', function (): void {
    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    expect($service->isTrustedDevice($customer, null))->toBeFalse()
        ->and($service->isTrustedDevice($customer, ''))->toBeFalse()
        ->and($service->isTrustedDevice($customer, 'invalid-random-token'))->toBeFalse();
});

it('remembers device with hashed token and recognizes it on subsequent check per Doc 08 §4', function (): void {
    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $rawToken = $service->rememberDevice($customer, 'MacBook Pro', '192.168.1.1');

    expect($rawToken)->toBeString()->toHaveLength(64);

    // Verify token is hashed at rest
    $record = CustomerTrustedDevice::where('customer_id', $customer->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->device_name)->toBe('MacBook Pro')
        ->and($record->device_token_hash)->toBe(hash('sha256', $rawToken))
        ->and($record->device_token_hash)->not->toBe($rawToken);

    // Recognizes device with the raw token
    expect($service->isTrustedDevice($customer, $rawToken))->toBeTrue();
});

it('creates challenge with hashed OTP and dispatches notification per Doc 08 §4', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $result = $service->createChallenge(
        customer: $customer,
        deviceName: 'Chrome on Windows',
        ipAddress: '10.0.0.1',
        userAgent: 'Mozilla/5.0 Chrome/120'
    );

    expect($result)->toHaveKeys(['challenge_token', 'expires_at']);

    $challenge = CustomerDeviceChallenge::where('challenge_token', $result['challenge_token'])->first();
    expect($challenge)->not->toBeNull()
        ->and($challenge->customer_id)->toBe($customer->id)
        ->and($challenge->attempts)->toBe(0)
        ->and($challenge->device_name)->toBe('Chrome on Windows');

    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification) use ($challenge): bool {
            // Verify notification code matches the hash in DB
            return hash('sha256', $notification->code) === $challenge->otp_hash
                && strlen($notification->code) === 6;
        }
    );
});

it('invalidates existing pending challenges when creating a new challenge', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $first = $service->createChallenge($customer, 'Device A');
    $second = $service->createChallenge($customer, 'Device B');

    expect(CustomerDeviceChallenge::where('challenge_token', $first['challenge_token'])->exists())->toBeFalse()
        ->and(CustomerDeviceChallenge::where('challenge_token', $second['challenge_token'])->exists())->toBeTrue();
});

it('verifies challenge successfully and single-use deletes challenge', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $challengeData = $service->createChallenge($customer);
    $challenge = CustomerDeviceChallenge::where('challenge_token', $challengeData['challenge_token'])->first();

    // Capture the OTP sent via notification
    $sentCode = null;
    Notification::assertSentTo(
        $customer,
        DeviceVerificationOtpNotification::class,
        function (DeviceVerificationOtpNotification $notification) use (&$sentCode): bool {
            $sentCode = $notification->code;

            return true;
        }
    );

    expect($sentCode)->not->toBeNull();

    $result = $service->verifyChallenge($challengeData['challenge_token'], (string) $sentCode);

    expect($result->successful)->toBeTrue()
        ->and($result->customer?->id)->toBe($customer->id);

    // Challenge is deleted (single-use)
    expect(CustomerDeviceChallenge::where('challenge_token', $challengeData['challenge_token'])->exists())->toBeFalse();
});

it('rejects invalid code and increments attempt counter', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $challengeData = $service->createChallenge($customer);

    $result = $service->verifyChallenge($challengeData['challenge_token'], '000000');

    expect($result->successful)->toBeFalse()
        ->and($result->errorCode)->toBe('invalid_verification_code');

    $challenge = CustomerDeviceChallenge::where('challenge_token', $challengeData['challenge_token'])->first();
    expect($challenge?->attempts)->toBe(1);
});

it('destroys challenge when 3 failed attempts are reached', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $challengeData = $service->createChallenge($customer);

    // Attempt 1
    $service->verifyChallenge($challengeData['challenge_token'], '000001');
    // Attempt 2
    $service->verifyChallenge($challengeData['challenge_token'], '000002');
    // Attempt 3 (locks out challenge)
    $result = $service->verifyChallenge($challengeData['challenge_token'], '000003');

    expect($result->successful)->toBeFalse()
        ->and($result->errorCode)->toBe('challenge_limit_exceeded');

    // Challenge record was destroyed
    expect(CustomerDeviceChallenge::where('challenge_token', $challengeData['challenge_token'])->exists())->toBeFalse();
});

it('rejects expired challenge and cleans it up per Doc 08 §4', function (): void {
    Notification::fake();

    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $challengeData = $service->createChallenge($customer);

    // Fast-forward past 10-minute expiry window
    Carbon::setTestNow(Carbon::now()->addMinutes(11));

    $result = $service->verifyChallenge($challengeData['challenge_token'], '123456');

    expect($result->successful)->toBeFalse()
        ->and($result->errorCode)->toBe('invalid_or_expired_challenge');

    expect(CustomerDeviceChallenge::where('challenge_token', $challengeData['challenge_token'])->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('revokes a specific device and all devices on demand per Doc 22 B7.5', function (): void {
    $service = new DeviceVerificationService;
    $customer = Customer::factory()->create();

    $token1 = $service->rememberDevice($customer, 'Device 1');
    $token2 = $service->rememberDevice($customer, 'Device 2');

    $devices = CustomerTrustedDevice::where('customer_id', $customer->id)->get();
    expect($devices)->toHaveCount(2);

    // Revoke single device by ULID
    $device1 = $devices->first();
    expect($device1)->not->toBeNull();
    $revoked = $service->revokeDevice($customer, $device1->ulid);
    expect($revoked)->toBeTrue();

    expect(CustomerTrustedDevice::where('customer_id', $customer->id)->count())->toBe(1);

    // Revoke all remaining devices
    $count = $service->revokeAllDevices($customer);
    expect($count)->toBe(1)
        ->and(CustomerTrustedDevice::where('customer_id', $customer->id)->count())->toBe(0);
});
