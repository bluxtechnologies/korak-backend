<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Notifications\LockoutNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoginLockoutService
{
    /**
     * Number of consecutive failed attempts before lockout occurs (Doc 08 §2).
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes (Doc 08 §2).
     */
    public const LOCKOUT_DURATION_MINUTES = 15;

    /**
     * Determine if the customer account is currently locked.
     */
    public function isLocked(Customer $customer): bool
    {
        return Cache::has($this->lockoutKey($customer));
    }

    /**
     * Record a failed login attempt for the customer.
     *
     * Returns the total consecutive failed attempts.
     * If the threshold (5) is reached, locks the account and dispatches LockoutNotification.
     */
    public function recordFailedAttempt(Customer $customer): int
    {
        if ($this->isLocked($customer)) {
            return self::MAX_FAILED_ATTEMPTS;
        }

        $attemptsKey = $this->attemptsKey($customer);
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->lock($customer);

            return $attempts;
        }

        Cache::put($attemptsKey, $attempts, now()->addMinutes(self::LOCKOUT_DURATION_MINUTES));

        return $attempts;
    }

    /**
     * Get the current count of consecutive failed attempts.
     */
    public function failedAttempts(Customer $customer): int
    {
        return (int) Cache::get($this->attemptsKey($customer), 0);
    }

    /**
     * Lock the customer account for the configured lockout duration and dispatch notification.
     */
    public function lock(Customer $customer, ?int $minutes = null): void
    {
        $duration = $minutes ?? self::LOCKOUT_DURATION_MINUTES;

        Cache::put($this->lockoutKey($customer), true, now()->addMinutes($duration));
        Cache::forget($this->attemptsKey($customer));

        try {
            $customer->notify(new LockoutNotification($duration));
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch LockoutNotification: '.$e->getMessage(), [
                'customer_id' => $customer->id,
            ]);
        }
    }

    /**
     * Unlock the customer account and reset failed attempts.
     */
    public function unlock(Customer $customer): void
    {
        Cache::forget($this->lockoutKey($customer));
        Cache::forget($this->attemptsKey($customer));
    }

    /**
     * Clear failed attempts on successful login.
     */
    public function clearFailedAttempts(Customer $customer): void
    {
        Cache::forget($this->attemptsKey($customer));
    }

    /**
     * Generate cache key for tracking failed login attempts.
     */
    protected function attemptsKey(Customer $customer): string
    {
        return 'auth:attempts:customer:'.$customer->id;
    }

    /**
     * Generate cache key for account lockout state.
     */
    protected function lockoutKey(Customer $customer): string
    {
        return 'auth:lockout:customer:'.$customer->id;
    }
}
