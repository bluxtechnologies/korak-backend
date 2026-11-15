<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyDeviceRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Notifications\DeviceVerifiedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class VerifyDeviceController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected DeviceVerificationService $deviceService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming device OTP verification requests.
     */
    public function __invoke(VerifyDeviceRequest $request): JsonResponse
    {
        $challengeToken = (string) $request->input('challenge_token');
        $code = (string) $request->input('code');

        $result = $this->deviceService->verifyChallenge($challengeToken, $code);

        if (! $result->successful || ! $result->customer) {
            $this->auditLogger->log(
                event: AuditEventName::DEVICE_VERIFICATION_FAILED,
                metadata: ['reason' => $result->errorCode]
            );

            return ApiResponse::error(
                message: $result->errorMessage ?? 'Invalid verification code.',
                code: $result->errorCode ?? 'invalid_verification_code',
                status: $result->status,
            );
        }

        $customer = $result->customer;

        $deviceName = (string) ($request->input('device_name') ?: $request->userAgent() ?: 'Browser');
        $customer->notify(new DeviceVerifiedNotification($deviceName, $request->ip()));

        $this->auditLogger->log(
            event: AuditEventName::DEVICE_VERIFICATION_SUCCESS,
            actor: $customer,
            subject: $customer
        );

        $this->auditLogger->log(
            event: AuditEventName::AUTH_LOGIN_SUCCESS,
            actor: $customer,
            subject: $customer
        );

        // Authenticate on the customer guard
        Auth::guard('customer')->login($customer);

        // Regenerate session ID if session is present (Doc 08 §3)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Handle "remember device" option (Doc 08 §4)
        if ($request->boolean('remember_device')) {
            $deviceName = $request->input('device_name') ?: $request->userAgent() ?: 'Browser';
            $rawToken = $this->deviceService->rememberDevice($customer, $deviceName, $request->ip());

            $this->auditLogger->log(
                event: AuditEventName::DEVICE_TRUSTED,
                actor: $customer,
                subject: $customer,
                metadata: ['device_name' => $deviceName]
            );

            $response = (new CustomerResource($customer))
                ->additional(['meta' => ['device_token' => $rawToken]])
                ->response();

            $response->withCookie(cookie(
                name: 'device_token',
                value: $rawToken,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: config('session.domain'),
                secure: (bool) config('session.secure', false),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));

            return $response;
        }

        return (new CustomerResource($customer))->response();
    }
}
