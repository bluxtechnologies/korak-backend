<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Get the authenticated customer's profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        return ApiResponse::success(new CustomerResource($customer));
    }

    /**
     * Update the authenticated customer's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $validated = $request->validated();

        if (empty($validated)) {
            return ApiResponse::success(new CustomerResource($customer));
        }

        $customer->fill($validated);

        if ($customer->isDirty('email')) {
            $customer->email_verified_at = null;
        }

        $customer->save();

        $this->auditLogger->log(
            event: AuditEventName::PROFILE_UPDATED,
            actor: $customer,
            subject: $customer,
            metadata: ['updated_fields' => array_keys($validated)],
        );

        return ApiResponse::success(new CustomerResource($customer->fresh()));
    }
}
