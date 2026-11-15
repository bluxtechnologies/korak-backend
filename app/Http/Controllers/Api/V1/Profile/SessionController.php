<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\RevokeOtherSessionsRequest;
use App\Http\Resources\Api\V1\SessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * List all active sessions for the authenticated customer.
     *
     * Per Doc 08 §3 and Roadmap B11.3.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $sessionTable = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($sessionTable)) {
            return ApiResponse::success([]);
        }

        $sessions = DB::table($sessionTable)
            ->where('user_id', $customer->getAuthIdentifier())
            ->orderBy('last_activity', 'desc')
            ->get();

        return ApiResponse::success(SessionResource::collection($sessions));
    }

    /**
     * Sign out all other sessions for the authenticated customer.
     *
     * Requires re-authentication with current_password per Roadmap B11.3 & Doc 08 §3.
     */
    public function destroyOther(RevokeOtherSessionsRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

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

        $this->auditLogger->log(
            event: AuditEventName::SESSION_REVOKED_OTHER,
            actor: $customer,
            subject: $customer
        );

        return ApiResponse::success([
            'message' => 'All other sessions have been signed out.',
        ]);
    }

    /**
     * Sign out a specific session by its opaque identifier.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $sessionTable = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($sessionTable)) {
            return ApiResponse::error('Session not found.', 'session_not_found', 404);
        }

        $sessions = DB::table($sessionTable)
            ->where('user_id', $customer->getAuthIdentifier())
            ->get();

        $matched = $sessions->first(
            fn (object $s): bool => hash_equals(hash('sha256', (string) $s->id), $id)
        );

        if (! $matched) {
            return ApiResponse::error('Session not found.', 'session_not_found', 404);
        }

        DB::table($sessionTable)->where('id', $matched->id)->delete();

        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        if ($currentSessionId !== null && hash_equals((string) $matched->id, $currentSessionId)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->auditLogger->log(
            event: AuditEventName::SESSION_REVOKED,
            actor: $customer,
            subject: $customer,
            metadata: ['session_hash' => $id]
        );

        return ApiResponse::success([
            'message' => 'Session signed out successfully.',
        ]);
    }
}
