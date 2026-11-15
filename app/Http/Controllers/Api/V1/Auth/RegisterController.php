<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AuditEventName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

final class RegisterController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Handle incoming registration requests.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        event(new Registered($customer));

        $this->auditLogger->log(
            event: AuditEventName::AUTH_REGISTERED,
            actor: $customer,
            subject: $customer,
            metadata: [
                'username' => $customer->username,
                'email' => $customer->email,
            ]
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }
}
