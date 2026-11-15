<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceVerificationService;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LoginLockoutService $lockoutService,
        protected DeviceVerificationService $deviceService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming customer session login requests.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $identifier = $request->identifier();
        $password = (string) $request->input('password');

        // Look up by email if formatted as email, otherwise by username (Doc 04 §3).
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        $customer = $isEmail
            ? Customer::where('email', $identifier)->first()
            : Customer::where('username', $identifier)->first();

        // 1. Account does not exist: run dummy hash to protect against timing attacks (Doc 08 §1).
        if (! $customer) {
            Hash::check($password, '$2y$12$e8Y6lF1wO11Xv.o6Y/eI8.qZgH47e7u7vTfVpG/t9n1W1s7X4n0e2');

            $this->auditLogger->log(
                event: AuditEventName::AUTH_LOGIN_FAILED,
                metadata: ['identifier' => $identifier]
            );

            return ApiResponse::error(
                message: 'Invalid credentials.',
                code: 'invalid_credentials',
                status: 401
            );
        }

        // 2. Account is locked: run hash check to maintain constant time, then return identical generic error (Doc 08 §2 & Doc 22 B6.4).
        if ($this->lockoutService->isLocked($customer)) {
            Hash::check($password, $customer->password);

            $this->auditLogger->log(
                event: AuditEventName::AUTH_LOGIN_FAILED,
                actor: $customer,
                subject: $customer,
                metadata: ['reason' => 'account_locked']
            );

            return ApiResponse::error(
                message: 'Invalid credentials.',
                code: 'invalid_credentials',
                status: 401
            );
        }

        // 3. Password mismatch: record failed attempt (triggers 15-min lockout on 5th attempt) and return identical generic error.
        if (! Hash::check($password, $customer->password)) {
            $attempts = $this->lockoutService->recordFailedAttempt($customer);

            $this->auditLogger->log(
                event: AuditEventName::AUTH_LOGIN_FAILED,
                actor: $customer,
                subject: $customer,
                metadata: ['reason' => 'invalid_password']
            );

            if ($attempts >= LoginLockoutService::MAX_FAILED_ATTEMPTS) {
                $this->auditLogger->log(
                    event: AuditEventName::AUTH_LOCKOUT,
                    actor: $customer,
                    subject: $customer
                );
            }

            return ApiResponse::error(
                message: 'Invalid credentials.',
                code: 'invalid_credentials',
                status: 401
            );
        }

        // 4. Successful credentials: clear failed attempts
        $this->lockoutService->clearFailedAttempts($customer);

        // 5. Device verification check (Doc 08 §4, Doc 22 B7.2)
        $deviceToken = (string) (
            $request->header('X-Device-Token')
            ?: $request->cookie('device_token')
            ?: $request->input('device_token')
        );

        $deviceName = (string) ($request->input('device_name') ?: $request->userAgent() ?: 'Unknown Device');

        if (config('auth.device_verification_enabled', true) && ! $this->deviceService->isTrustedDevice($customer, $deviceToken)) {
            $challenge = $this->deviceService->createChallenge(
                customer: $customer,
                deviceName: $deviceName,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $this->auditLogger->log(
                event: AuditEventName::DEVICE_CHALLENGE_ISSUED,
                actor: $customer,
                subject: $customer,
                metadata: ['device_name' => $deviceName]
            );

            return ApiResponse::success([
                'status' => 'device_verification_required',
                'challenge_token' => $challenge['challenge_token'],
                'expires_at' => $challenge['expires_at']->toIso8601String(),
            ]);
        }

        // 6. Device is recognized: authenticate on customer guard
        Auth::guard('customer')->login($customer);

        $this->auditLogger->log(
            event: AuditEventName::AUTH_LOGIN_SUCCESS,
            actor: $customer,
            subject: $customer
        );

        // Regenerate session ID if session is present (Doc 08 §3)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return (new CustomerResource($customer))->response();
    }
}
