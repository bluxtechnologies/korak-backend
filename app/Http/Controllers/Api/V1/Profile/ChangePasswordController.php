<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Notifications\PasswordChangedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChangePasswordController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Change the customer's password.
     *
     * Per Doc 08 §1, Doc 11 §4, and Roadmap B11.2:
     * Requires current password verification, updates password, invalidates other sessions,
     * dispatches PasswordChangedNotification, and logs audit event.
     */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $customer->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        // Dispatch queued security notification (mail + database)
        $customer->notify(new PasswordChangedNotification);

        // Audit the password change
        $this->auditLogger->log(
            event: AuditEventName::AUTH_PASSWORD_CHANGED,
            actor: $customer,
            subject: $customer
        );

        // Invalidate other database sessions if sessions table exists
        $sessionTable = (string) config('session.table', 'sessions');
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        if (Schema::hasTable($sessionTable)) {
            $query = DB::table($sessionTable)
                ->where('user_id', $customer->getAuthIdentifier());

            if ($currentSessionId !== null) {
                $query->where('id', '!=', $currentSessionId);
            }

            $query->delete();
        }

        return ApiResponse::success([
            'message' => 'Password changed successfully.',
        ]);
    }
}
