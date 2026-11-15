<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Notifications\PasswordChangedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming reset password request.
     *
     * Per Doc 08 §1 and Doc 22 (B5.2, B5.3):
     * Single-use, expiring token. Invalidates sessions upon successful reset.
     * Generic error for invalid token or unknown user (anti-enumeration).
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, #[\SensitiveParameter] string $password): void {
                $customer->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($customer));

                $customer->notify(new PasswordChangedNotification);

                $this->auditLogger->log(
                    event: AuditEventName::AUTH_PASSWORD_RESET_COMPLETED,
                    actor: $customer,
                    subject: $customer
                );

                // Clear any lockout and failed attempts upon password reset
                app(LoginLockoutService::class)->unlock($customer);

                // Invalidate all active database sessions for this customer (Doc 08 §3, Roadmap B5.3)
                $sessionTable = (string) config('session.table', 'sessions');
                if (Schema::hasTable($sessionTable)) {
                    DB::table($sessionTable)
                        ->where('user_id', $customer->getAuthIdentifier())
                        ->delete();
                }
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return ApiResponse::success([
                'message' => 'Your password has been reset.',
            ]);
        }

        // Return generic 400 Bad Request for any failed status (anti-enumeration).
        return ApiResponse::error(
            'Invalid or expired password reset token.',
            'invalid_reset_token',
            400
        );
    }
}
