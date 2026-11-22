<?php

namespace App\Services\Wallet;

use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Services\Providers\FinityPayService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DedicatedAccountService
{
    /**
     * Create a new DedicatedAccountService instance.
     */
    public function __construct(
        protected WalletService $walletService,
        protected FinityPayService $finityPayService,
    ) {}

    /**
     * Generate or retrieve a dedicated virtual account for the customer.
     *
     * @return array{status: string, code?: string, message?: string, temp_error?: string, data?: array<string, mixed>}
     */
    public function generateAccount(Customer $customer, ?string $phone = null): array
    {
        // Check if customer already has an active virtual account
        /** @var CustomerVirtualAccount|null $existing */
        $existing = CustomerVirtualAccount::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return [
                'status' => 'success',
                'data' => [
                    'id' => $existing->ulid,
                    'bank_name' => $existing->bank_name,
                    'bank_code' => $existing->bank_code,
                    'account_number' => $existing->account_number,
                    'account_name' => $existing->account_name,
                    'account_reference' => $existing->account_reference,
                    'status' => $existing->status,
                ],
            ];
        }

        $wallet = $this->walletService->getOrCreateWallet($customer);

        try {
            return $this->finityPayService->createVirtualAccount(
                customer: $customer,
                wallet: $wallet,
                phone: $phone ?: $customer->phone
            );
        } catch (ConnectException|RequestException|ConnectionException $e) {
            Log::error('DedicatedAccountService upstream connection exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('DedicatedAccountService unexpected exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'code' => 'provider_unavailable',
                'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
                'temp_error' => $e->getMessage(),
            ];
        }
    }
}
