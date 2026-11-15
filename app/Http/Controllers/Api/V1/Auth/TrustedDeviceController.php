<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrustedDeviceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedDeviceController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected DeviceVerificationService $deviceService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * List all trusted devices for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $devices = $customer->trustedDevices()->orderByDesc('last_used_at')->get();

        return TrustedDeviceResource::collection($devices)->response();
    }

    /**
     * Revoke a specific trusted device by ULID.
     */
    public function destroy(Request $request, string $ulid): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $revoked = $this->deviceService->revokeDevice($customer, $ulid);

        if (! $revoked) {
            return ApiResponse::error(
                message: 'Trusted device not found.',
                code: 'device_not_found',
                status: 404
            );
        }

        $this->auditLogger->log(
            event: AuditEventName::DEVICE_REVOKED,
            actor: $customer,
            subject: $customer,
            metadata: ['device_ulid' => $ulid]
        );

        return ApiResponse::success([
            'message' => 'Device revoked successfully.',
        ]);
    }

    /**
     * Revoke all trusted devices for the authenticated customer.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $count = $this->deviceService->revokeAllDevices($customer);

        $this->auditLogger->log(
            event: AuditEventName::DEVICE_REVOKED_ALL,
            actor: $customer,
            subject: $customer,
            metadata: ['revoked_count' => $count]
        );

        return ApiResponse::success([
            'message' => 'All devices revoked successfully.',
            'revoked_count' => $count,
        ]);
    }
}
