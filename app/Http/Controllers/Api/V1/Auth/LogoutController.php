<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class LogoutController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Invalidate the current session and logout the customer per Doc 08 §3.
     */
    public function __invoke(Request $request): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $this->auditLogger->log(
                event: AuditEventName::AUTH_LOGOUT,
                actor: $customer,
                subject: $customer
            );
        }

        Auth::guard('customer')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::noContent();
    }
}
