<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming forgot-password request.
     *
     * Per Doc 08 §1 and Doc 22 (B5.1):
     * Anti-enumeration: returns identical response whether the account exists or not.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');

        // Broker attempts to send the reset link (queues ResetPasswordNotification if customer exists).
        Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        $customer = Customer::where('email', $email)->first();

        $this->auditLogger->log(
            event: AuditEventName::AUTH_PASSWORD_RESET_REQUESTED,
            actor: $customer,
            subject: $customer,
            metadata: ['email' => $email]
        );

        return ApiResponse::success([
            'message' => 'If that email address is in our database, we have sent a password reset link.',
        ]);
    }
}
