<?php

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\GenerateDedicatedAccountRequest;
use App\Http\Resources\Api\V1\CustomerVirtualAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\DedicatedAccountService;
use App\Services\Wallet\WalletService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VirtualAccountController extends Controller
{
    /**
     * Create a new VirtualAccountController instance.
     */
    public function __construct(
        protected WalletService $walletService,
        protected FinityPayService $finityPayService,
        protected DedicatedAccountService $dedicatedAccountService,
    ) {}

    /**
     * Get the authenticated customer's dedicated virtual account details.
     * GET /api/v1/wallet/dedicated-account
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var CustomerVirtualAccount|null $virtualAccount */
        $virtualAccount = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if (! $virtualAccount) {
            return ApiResponse::success(
                data: null,
                meta: [
                    'has_account' => false,
                    'requires_phone' => empty($customer->phone),
                ]
            );
        }

        return ApiResponse::success(
            data: new CustomerVirtualAccountResource($virtualAccount),
            meta: [
                'has_account' => true,
            ]
        );
    }

    /**
     * Generate or retrieve a dedicated virtual bank account for the authenticated customer.
     * POST /api/v1/wallet/dedicated-account
     */
    public function store(GenerateDedicatedAccountRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        // Check if customer already has an active virtual account
        /** @var CustomerVirtualAccount|null $existing */
        $existing = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return ApiResponse::success(
                data: new CustomerVirtualAccountResource($existing),
                meta: ['has_account' => true, 'already_existed' => true]
            );
        }

        // Update profile names and phone if provided in request
        $dirty = false;
        if ($request->filled('first_name') && ! $customer->first_name) {
            $customer->first_name = (string) $request->input('first_name');
            $dirty = true;
        }
        if ($request->filled('last_name') && ! $customer->last_name) {
            $customer->last_name = (string) $request->input('last_name');
            $dirty = true;
        }
        if ($request->filled('phone')) {
            $customer->phone = (string) $request->input('phone');
            $dirty = true;
        }
        if ($dirty) {
            $customer->save();
        }

        $wallet = $this->walletService->getOrCreateWallet($customer);

        try {
            $result = $this->dedicatedAccountService->generateAccount(
                customer: $customer,
                phone: $customer->phone
            );
        } catch (ConnectException|RequestException|ConnectionException $e) {
            Log::error('Dedicated virtual account connection exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            Log::error('Dedicated virtual account generation exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ], 502);
        }

        if ($result['status'] !== 'success') {
            Log::warning('Dedicated virtual account generation failed', [
                'customer_id' => $customer->id,
                'error' => $result['message'] ?? null,
            ]);

            $rawMessage = $result['message'] ?? 'Payment provider is currently undergoing maintenance. Please try again shortly.';
            $code = $result['code'] ?? 'provider_unavailable';

            if (preg_match('/cURL|curl|resolve host|connection|timeout|failed to connect|undergoing maintenance|unauthorized|api key|invalid.*key/i', $rawMessage)) {
                $rawMessage = 'Payment provider is currently undergoing maintenance. Please try again shortly.';
                $code = 'provider_unavailable';
            }

            return response()->json([
                'status' => 'error',
                'code' => $code,
                'message' => $rawMessage,
                'temp_error' => $result['temp_error'] ?? $result['message'] ?? $rawMessage,
            ], 502);
        }

        /** @var CustomerVirtualAccount|null $account */
        $account = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if (! $account) {
            /** @var CustomerVirtualAccount $account */
            $account = CustomerVirtualAccount::where('customer_id', $customer->id)->firstOrFail();
        }

        return ApiResponse::success(
            data: new CustomerVirtualAccountResource($account),
            meta: ['has_account' => true, 'created' => true],
            status: 201
        );
    }
}
