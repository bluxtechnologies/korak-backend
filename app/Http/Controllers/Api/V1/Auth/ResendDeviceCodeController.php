<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResendDeviceCodeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceVerificationService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

final class ResendDeviceCodeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected DeviceVerificationService $deviceService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming device verification OTP resend requests.
     */
    public function __invoke(ResendDeviceCodeRequest $request): JsonResponse
    {
        $challengeToken = (string) $request->input('challenge_token');
        $identifier = (string) ($request->input('login') ?: $request->input('email') ?: $request->input('phone_number') ?: '');
        $deviceName = (string) ($request->input('device_name') ?: $request->userAgent() ?: 'Unknown Device');

        $result = null;

        // 1. Try finding and renewing existing challenge via challenge_token
        if ($challengeToken !== '') {
            $result = $this->deviceService->resendChallenge(
                challengeToken: $challengeToken,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                deviceName: $deviceName,
            );
        }

        // 2. Fallback to customer lookup if challenge_token was invalid/expired/missing but identifier was given
        if (($result === null || ! $result['successful']) && $identifier !== '') {
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

            if ($isEmail) {
                $customer = Customer::where('email', $identifier)->first();
            } else {
                $customer = Customer::where('username', $identifier)
                    ->orWhere('phone_number', $identifier)
                    ->orWhere('phone', $identifier)
                    ->first();

                if (! $customer && preg_match('/\d{7,}/', $identifier)) {
                    $cleaned = (string) preg_replace('/[^\d+]/', '', $identifier);
                    $customer = Customer::where('phone_number', $cleaned)
                        ->orWhere('phone', $cleaned)
                        ->first();
                }
            }

            if ($customer) {
                $result = $this->deviceService->resendForCustomer(
                    customer: $customer,
                    deviceName: $deviceName,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }
        }

        if ($result === null || ! $result['successful']) {
            return ApiResponse::error(
                message: $result['message'] ?? 'Invalid or expired verification challenge. Please log in again.',
                code: $result['code'] ?? 'invalid_or_expired_challenge',
                status: $result['status'] ?? 400,
            );
        }

        /** @var Customer|null $customer */
        $customer = $result['customer'] ?? null;

        if ($customer) {
            $this->auditLogger->log(
                event: AuditEventName::DEVICE_CHALLENGE_ISSUED,
                actor: $customer,
                subject: $customer,
                metadata: [
                    'device_name' => $deviceName,
                    'action' => 'resend_otp',
                ]
            );
        }

        $expiresAt = $result['expires_at'] ?? null;
        $expiresAtString = $expiresAt instanceof CarbonInterface
            ? $expiresAt->toIso8601String()
            : (string) $expiresAt;

        return ApiResponse::success([
            'status' => 'device_verification_required',
            'requires_device_verification' => true,
            'message' => 'Verification code resent successfully.',
            'challenge_token' => $result['challenge_token'],
            'expires_at' => $expiresAtString,
        ]);
    }
}
