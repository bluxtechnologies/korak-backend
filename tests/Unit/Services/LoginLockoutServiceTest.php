<?php

use App\Models\Customer;
use App\Notifications\LockoutNotification;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('initializes customer with zero failed attempts and not locked', function (): void {
    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    expect($service->isLocked($customer))->toBeFalse()
        ->and($service->failedAttempts($customer))->toBe(0);
});

it('increments failed attempts up to threshold without locking', function (): void {
    Notification::fake();

    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    for ($i = 1; $i <= 4; $i++) {
        $attempts = $service->recordFailedAttempt($customer);
        expect($attempts)->toBe($i)
            ->and($service->failedAttempts($customer))->toBe($i)
            ->and($service->isLocked($customer))->toBeFalse();
    }

    Notification::assertNothingSent();
});

it('locks account and dispatches LockoutNotification on 5th failed attempt per Doc 08 §2', function (): void {
    Notification::fake();

    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    for ($i = 1; $i <= 4; $i++) {
        $service->recordFailedAttempt($customer);
    }

    // 5th failed attempt triggers lockout
    $attempts = $service->recordFailedAttempt($customer);

    expect($attempts)->toBe(5)
        ->and($service->isLocked($customer))->toBeTrue();

    Notification::assertSentTo(
        $customer,
        LockoutNotification::class,
        function (LockoutNotification $notification): bool {
            return $notification->durationMinutes === 15;
        }
    );
});

it('does not send multiple lockout emails if failed attempts continue while locked', function (): void {
    Notification::fake();

    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    for ($i = 1; $i <= 5; $i++) {
        $service->recordFailedAttempt($customer);
    }

    // Notification was sent once
    Notification::assertSentTimes(LockoutNotification::class, 1);

    // 6th and 7th failed attempt while already locked
    $service->recordFailedAttempt($customer);
    $service->recordFailedAttempt($customer);

    // Must NOT send additional notifications
    Notification::assertSentTimes(LockoutNotification::class, 1);
});

it('unlocks account and resets failed attempts', function (): void {
    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    $service->lock($customer);
    expect($service->isLocked($customer))->toBeTrue();

    $service->unlock($customer);
    expect($service->isLocked($customer))->toBeFalse()
        ->and($service->failedAttempts($customer))->toBe(0);
});

it('clears failed attempts on demand', function (): void {
    $service = new LoginLockoutService;
    $customer = Customer::factory()->create();

    $service->recordFailedAttempt($customer);
    $service->recordFailedAttempt($customer);
    expect($service->failedAttempts($customer))->toBe(2);

    $service->clearFailedAttempts($customer);
    expect($service->failedAttempts($customer))->toBe(0);
});
