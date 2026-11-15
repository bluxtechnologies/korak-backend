<?php

namespace App\Services\Bills;

use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillService
{
    /**
     * Create a new bill service instance.
     */
    public function __construct(
        protected WalletService $walletService,
        protected FinityPayService $finityPayService,
    ) {}

    /**
     * Purchase a bill/VTU service for a customer.
     *
     * Per Doc 14 §1-§3 & Roadmap B17:
     * 1. Atomically debit customer wallet with row locking.
     * 2. Create BillOrder with status 'pending'.
     * 3. Call provider API.
     * 4. Settle on success or auto-refund on immediate failure.
     *
     * @param  array<string, mixed>  $data
     */
    public function purchaseBill(Customer $customer, array $data, ?string $idempotencyKey = null): BillOrder
    {
        $amountInKobo = (int) $data['amount'];
        $faceAmountInKobo = isset($data['face_amount']) ? (int) $data['face_amount'] : $amountInKobo;
        $discountInKobo = max(0, $faceAmountInKobo - $amountInKobo);

        $reference = $idempotencyKey ?: 'BIL-'.strtoupper(Str::random(12));

        $wallet = $this->walletService->getOrCreateWallet($customer);

        // 1 & 2: Atomically debit wallet and record pending order
        /** @var BillOrder $billOrder */
        $billOrder = DB::transaction(function () use ($customer, $wallet, $data, $amountInKobo, $faceAmountInKobo, $discountInKobo, $reference): BillOrder {
            $this->walletService->debit(
                wallet: $wallet,
                amount: $amountInKobo,
                type: LedgerEntryType::ORDER_DEBIT,
                description: "VTU/Bill Purchase: {$data['service_name']} [{$data['customer_identifier']}]",
                reference: $reference,
                metadata: [
                    'service_code' => $data['service_code'],
                    'customer_identifier' => $data['customer_identifier'],
                    'package_code' => $data['package_code'] ?? null,
                ]
            );

            return BillOrder::create([
                'customer_id' => $customer->id,
                'reference' => $reference,
                'category' => $data['category'],
                'service_code' => $data['service_code'],
                'service_name' => $data['service_name'],
                'package_code' => $data['package_code'] ?? null,
                'customer_identifier' => $data['customer_identifier'],
                'customer_name' => $data['customer_name'] ?? null,
                'amount' => $amountInKobo,
                'face_amount' => $faceAmountInKobo,
                'discount_amount' => $discountInKobo,
                'currency' => 'NGN',
                'status' => 'pending',
                'metadata' => $data['metadata'] ?? [],
            ]);
        });

        // 3: Dispatch provider call
        $amountInNgn = round($amountInKobo / 100, 2);

        try {
            $providerRes = $this->finityPayService->payBill([
                'service_code' => $billOrder->service_code,
                'package_code' => $billOrder->package_code,
                'customer_identifier' => $billOrder->customer_identifier,
                'amount' => $amountInNgn,
            ]);

            /** @var array<string, mixed> $resData */
            $resData = isset($providerRes['data']) && is_array($providerRes['data'])
                ? $providerRes['data']
                : [];

            $status = strtolower((string) ($resData['status'] ?? $providerRes['status'] ?? ''));

            if ($status === 'success') {
                $billOrder->update([
                    'status' => 'completed',
                    'provider_reference' => $resData['provider_reference'] ?? $resData['reference'] ?? null,
                    'token' => $resData['token'] ?? null,
                    'processed_at' => now(),
                ]);

                return $billOrder->fresh() ?? $billOrder;
            }

            if ($status === 'pending' || $status === 'processing') {
                $billOrder->update([
                    'status' => 'processing',
                    'provider_reference' => $resData['provider_reference'] ?? $resData['reference'] ?? null,
                ]);

                return $billOrder->fresh() ?? $billOrder;
            }

            // Immediate failure reported by provider -> auto-refund wallet
            $this->walletService->credit(
                wallet: $wallet,
                amount: $amountInKobo,
                type: LedgerEntryType::REFUND,
                description: "Refund: Failed {$billOrder->service_name} [{$billOrder->reference}]",
                reference: 'REFUND-'.$billOrder->reference,
                metadata: [
                    'bill_order_id' => $billOrder->id,
                    'reason' => $providerRes['message'] ?? 'provider_immediate_failure',
                ]
            );

            $billOrder->update([
                'status' => 'failed',
                'metadata' => array_merge($billOrder->metadata ?? [], [
                    'error' => $providerRes['message'] ?? 'Provider execution failed',
                ]),
                'processed_at' => now(),
            ]);

            return $billOrder->fresh() ?? $billOrder;
        } catch (Exception $e) {
            Log::error('FinityPay bill purchase exception', [
                'reference' => $billOrder->reference,
                'message' => $e->getMessage(),
            ]);

            // Network / unhandled exception: Mark processing so webhook or reconciliation handles it
            $billOrder->update([
                'status' => 'processing',
                'metadata' => array_merge($billOrder->metadata ?? [], [
                    'exception' => $e->getMessage(),
                ]),
            ]);

            return $billOrder->fresh() ?? $billOrder;
        }
    }
}
