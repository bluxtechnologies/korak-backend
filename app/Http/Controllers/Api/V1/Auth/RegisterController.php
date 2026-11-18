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
use Illuminate\Support\Str;

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

        $name = isset($validated['name']) ? trim((string) $validated['name']) : null;
        $firstName = null;
        $lastName = null;

        if ($name !== null && $name !== '') {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? null;
        }

        $username = ! empty($validated['username'])
            ? (string) $validated['username']
            : $this->generateUniqueUsername($name, (string) $validated['email']);

        $customer = Customer::create([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'phone' => $validated['phone_number'],
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
                'phone_number' => $customer->phone_number,
            ]
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Auto-generate a unique username from name or email.
     */
    private function generateUniqueUsername(?string $name, string $email): string
    {
        $base = '';

        if ($name !== null && trim($name) !== '') {
            $base = Str::slug(trim($name), '_');
        }

        if ($base === '') {
            $base = Str::slug(Str::before($email, '@'), '_');
        }

        $base = preg_replace('/[^a-zA-Z0-9_]/', '', $base) ?: 'user';
        $base = substr($base, 0, 20);

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }

        $candidate = Str::lower($base);

        if (! Customer::where('username', $candidate)->exists()) {
            return $candidate;
        }

        for ($i = 0; $i < 10; $i++) {
            $suffix = (string) random_int(100, 9999);
            $candidate = Str::lower(substr($base, 0, 30 - strlen($suffix) - 1).'_'.$suffix);

            if (! Customer::where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'usr_'.Str::lower(Str::random(10));
    }
}
