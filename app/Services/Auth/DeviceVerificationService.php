<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerDeviceChallenge;
use App\Models\CustomerTrustedDevice;
use App\Notifications\DeviceVerificationOtpNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceVerificationService
{
    /**
     * Duration in minutes before an OTP expires (Doc 08 §4).
     */
    public const OTP_EXPIRY_MINUTES = 10;

    /**
     * Maximum failed OTP attempts before challenge is destroyed (Doc 08 §4).
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Check whether the presented raw device token is trusted for this customer.
     */
    public function isTrustedDevice(Customer $customer, ?string $rawDeviceToken): bool
    {
        if (empty($rawDeviceToken)) {
            return false;
        }

        $hash = hash('sha256', $rawDeviceToken);

        $trusted = CustomerTrustedDevice::where('customer_id', $customer->id)
            ->where('device_token_hash', $hash)
            ->first();

        if ($trusted) {
            $trusted->update(['last_used_at' => Carbon::now()]);

            return true;
        }

        return false;
    }

    /**
     * Create an OTP challenge for an unrecognised device and dispatch notification.
     *
     * @return array{challenge_token: string, expires_at: Carbon}
     */
    public function createChallenge(
        Customer $customer,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        // Invalidate any existing pending challenges for this customer
        CustomerDeviceChallenge::where('customer_id', $customer->id)->delete();

        $otp = sprintf('%06d', random_int(0, 999999));
        $challengeToken = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        CustomerDeviceChallenge::create([
            'customer_id' => $customer->id,
            'challenge_token' => $challengeToken,
            'otp_hash' => hash('sha256', $otp),
            'device_name' => $deviceName ?: 'Unknown Device',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ]);

        try {
            $customer->notify(new DeviceVerificationOtpNotification(
                code: $otp,
                deviceName: $deviceName ?: 'Unknown Device',
                ipAddress: $ipAddress,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch DeviceVerificationOtpNotification: '.$e->getMessage(), [
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ]);
        }

        return [
            'challenge_token' => $challengeToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Verify an OTP challenge.
     */
    public function verifyChallenge(string $challengeToken, string $code): DeviceVerificationResult
    {
        $challenge = CustomerDeviceChallenge::where('challenge_token', $challengeToken)->first();

        if (! $challenge) {
            return DeviceVerificationResult::failure(
                message: 'Invalid or expired verification challenge.',
                code: 'invalid_or_expired_challenge',
                status: 400
            );
        }

        if (Carbon::now()->isAfter($challenge->expires_at)) {
            $challenge->delete();

            return DeviceVerificationResult::failure(
                message: 'Invalid or expired verification challenge.',
                code: 'invalid_or_expired_challenge',
                status: 400
            );
        }

        if ($challenge->attempts >= self::MAX_ATTEMPTS) {
            $challenge->delete();

            return DeviceVerificationResult::failure(
                message: 'Too many failed verification attempts. Please log in again.',
                code: 'challenge_limit_exceeded',
                status: 400
            );
        }

        $codeHash = hash('sha256', $code);

        if (! hash_equals($challenge->otp_hash, $codeHash)) {
            $challenge->increment('attempts');

            if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                $challenge->delete();

                return DeviceVerificationResult::failure(
                    message: 'Too many failed verification attempts. Please log in again.',
                    code: 'challenge_limit_exceeded',
                    status: 400
                );
            }

            return DeviceVerificationResult::failure(
                message: 'Invalid verification code.',
                code: 'invalid_verification_code',
                status: 400
            );
        }

        $customer = $challenge->customer;

        // Single-use: delete challenge on success
        $challenge->delete();

        return DeviceVerificationResult::success($customer);
    }

    /**
     * Resend an OTP challenge for an existing active challenge.
     *
     * @return array{successful: bool, challenge_token?: string, expires_at?: Carbon, message?: string, code?: string, status?: int, customer?: Customer}
     */
    public function resendChallenge(
        string $challengeToken,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceName = null,
    ): array {
        $challenge = CustomerDeviceChallenge::where('challenge_token', $challengeToken)->first();

        if (! $challenge) {
            return [
                'successful' => false,
                'message' => 'Invalid or expired verification challenge. Please log in again.',
                'code' => 'invalid_or_expired_challenge',
                'status' => 400,
            ];
        }

        if (Carbon::now()->isAfter($challenge->expires_at)) {
            $challenge->delete();

            return [
                'successful' => false,
                'message' => 'Invalid or expired verification challenge. Please log in again.',
                'code' => 'invalid_or_expired_challenge',
                'status' => 400,
            ];
        }

        $otp = sprintf('%06d', random_int(0, 999999));
        $expiresAt = Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        $challenge->update([
            'otp_hash' => hash('sha256', $otp),
            'attempts' => 0,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress ?: $challenge->ip_address,
            'user_agent' => $userAgent ?: $challenge->user_agent,
            'device_name' => $deviceName ?: $challenge->device_name,
        ]);

        $customer = $challenge->customer;

        try {
            $customer->notify(new DeviceVerificationOtpNotification(
                code: $otp,
                deviceName: $challenge->device_name ?: 'Unknown Device',
                ipAddress: $ipAddress ?: $challenge->ip_address,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch DeviceVerificationOtpNotification on resend: '.$e->getMessage(), [
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ]);
        }

        return [
            'successful' => true,
            'challenge_token' => $challenge->challenge_token,
            'expires_at' => $expiresAt,
            'customer' => $customer,
        ];
    }

    /**
     * Issue or renew an OTP challenge for a customer directly.
     *
     * @return array{successful: bool, challenge_token: string, expires_at: Carbon, customer: Customer}
     */
    public function resendForCustomer(
        Customer $customer,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $challengeData = $this->createChallenge($customer, $deviceName, $ipAddress, $userAgent);

        return [
            'successful' => true,
            'challenge_token' => $challengeData['challenge_token'],
            'expires_at' => $challengeData['expires_at'],
            'customer' => $customer,
        ];
    }

    /**
     * Store a trusted device and return the plain-text token.
     */
    public function rememberDevice(Customer $customer, ?string $deviceName = null, ?string $ipAddress = null): string
    {
        $rawToken = Str::random(64);
        $hash = hash('sha256', $rawToken);

        CustomerTrustedDevice::create([
            'customer_id' => $customer->id,
            'device_token_hash' => $hash,
            'device_name' => $deviceName ?: 'Browser',
            'ip_address' => $ipAddress,
            'last_used_at' => Carbon::now(),
        ]);

        return $rawToken;
    }

    /**
     * Revoke a specific trusted device by ULID.
     */
    public function revokeDevice(Customer $customer, string $ulid): bool
    {
        $device = CustomerTrustedDevice::where('customer_id', $customer->id)
            ->where('ulid', $ulid)
            ->first();

        if (! $device) {
            return false;
        }

        return (bool) $device->delete();
    }

    /**
     * Revoke all trusted devices for a customer.
     */
    public function revokeAllDevices(Customer $customer): int
    {
        return CustomerTrustedDevice::where('customer_id', $customer->id)->delete();
    }
}
