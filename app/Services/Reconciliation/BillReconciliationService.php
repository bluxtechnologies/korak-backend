<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditEventName;
use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillReconciliationService
{
    /**
     * Create a new BillReconciliationService instance.
     */
    public function __construct(
        protected FinityPayService $finityPayService,
        protected WalletService $walletService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Reconcile a batch of in-flight / processing bill orders.
     *
     * Per Doc 14 §3, §7 & Roadmap B16.4.
     *
     * @return array{reconciled: int, completed: int, refunded: int, unchanged: int, errors: int}
     */
    public function reconcilePending(int $limit = 50): array
    {
        // Find orders created between 2 minutes ago and 3 days ago that are pending or processing
        $orders = BillOrder::whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $stats = [
            'reconciled' => 0,
            'completed' => 0,
            'refunded' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        foreach ($orders as $order) {
            try {
                $outcome = $this->reconcileSingleOrder($order);
                $stats['reconciled']++;
                $stats[$outcome]++;
            } catch (Exception $e) {
                $stats['errors']++;
                Log::error("Failed to reconcile bill order {$order->reference}", [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Reconcile a single bill order by reference or model.
     *
     * @return 'completed'|'refunded'|'unchanged'
     */
    public function reconcileSingleOrder(BillOrder $order): string
    {
        // Don't reconcile if already in terminal state
        if (in_array($order->status, ['completed', 'failed'], true)) {
            return 'unchanged';
        }

        $res = $this->finityPayService->getBillByReference($order->reference);

        if (! isset($res['status'])) {
            return 'unchanged';
        }

        $isSuccess = $res['status'] === 'success';
        /** @var array<string, mixed> $data */
        $data = isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
        $provStatus = strtolower((string) ($data['status'] ?? ($isSuccess ? 'success' : 'failed')));

        if ($provStatus === 'success') {
            $order->update([
                'status' => 'completed',
                'provider_reference' => $data['provider_reference'] ?? $data['reference'] ?? $order->provider_reference,
                'token' => $data['token'] ?? $order->token,
                'processed_at' => now(),
            ]);

            $this->auditLogger->log(
                event: AuditEventName::BILL_RECONCILED,
                subject: $order,
                metadata: [
                    'reference' => $order->reference,
                    'outcome' => 'completed',
                    'token' => ! empty($data['token']),
                ]
            );

            return 'completed';
        }

        if ($provStatus === 'failed' || $provStatus === 'cancelled' || $provStatus === 'error') {
            DB::transaction(function () use ($order, $res): void {
                $customer = $order->customer;
                $wallet = $this->walletService->getOrCreateWallet($customer);

                // Auto-refund customer wallet via the idempotent credit method
                $this->walletService->credit(
                    wallet: $wallet,
                    amount: $order->amount,
                    type: LedgerEntryType::REFUND,
                    description: "Refund: Failed {$order->service_name} [{$order->reference}]",
                    reference: 'REFUND-'.$order->reference,
                    metadata: [
                        'bill_order_id' => $order->id,
                        'reason' => $res['message'] ?? 'provider_reconciliation_failure',
                    ]
                );

                $order->update([
                    'status' => 'failed',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'reconciled_error' => $res['message'] ?? 'Failed during periodic reconciliation',
                    ]),
                    'processed_at' => now(),
                ]);
            });

            $this->auditLogger->log(
                event: AuditEventName::BILL_RECONCILED,
                subject: $order,
                metadata: [
                    'reference' => $order->reference,
                    'outcome' => 'refunded',
                    'amount' => $order->amount,
                ]
            );

            return 'refunded';
        }

        // Check if older than 24 hours with no positive confirmation from provider
        if ($order->created_at <= now()->subHours(24)) {
            DB::transaction(function () use ($order): void {
                $customer = $order->customer;
                $wallet = $this->walletService->getOrCreateWallet($customer);

                $this->walletService->credit(
                    wallet: $wallet,
                    amount: $order->amount,
                    type: LedgerEntryType::REFUND,
                    description: "Refund: Unresolved Timeout {$order->service_name} [{$order->reference}]",
                    reference: 'REFUND-'.$order->reference,
                    metadata: [
                        'bill_order_id' => $order->id,
                        'reason' => 'provider_timeout_24h_unresolved',
                    ]
                );

                $order->update([
                    'status' => 'failed',
                    'metadata' => array_merge($order->metadata ?? [], [
                        'reconciled_error' => 'Unresolved order exceeded 24-hour fulfillment window. Auto-refunded.',
                    ]),
                    'processed_at' => now(),
                ]);
            });

            $this->auditLogger->log(
                event: AuditEventName::BILL_RECONCILED,
                subject: $order,
                metadata: [
                    'reference' => $order->reference,
                    'outcome' => 'refunded_timeout_24h',
                    'amount' => $order->amount,
                ]
            );

            return 'refunded';
        }

        return 'unchanged';
    }
}
