<?php

namespace App\Services\Reconciliation;

use App\Enums\AuditEventName;
use App\Enums\FundingStatus;
use App\Enums\LedgerEntryType;
use App\Models\FundingRecord;
use App\Notifications\WalletFundedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FundingReconciliationService
{
    /**
     * Create a new FundingReconciliationService instance.
     */
    public function __construct(
        protected FinityPayService $finityPayService,
        protected WalletService $walletService,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Reconcile a batch of pending funding records against the payment gateway.
     *
     * Per Doc 14 §4, §7 & Roadmap B14.5.
     *
     * @return array{reconciled: int, credited: int, failed: int, unchanged: int, errors: int}
     */
    public function reconcilePending(int $limit = 50): array
    {
        $records = FundingRecord::where('status', FundingStatus::PENDING)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $stats = [
            'reconciled' => 0,
            'credited' => 0,
            'failed' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        foreach ($records as $record) {
            try {
                $outcome = $this->reconcileSingleRecord($record);
                $stats['reconciled']++;
                $stats[$outcome]++;
            } catch (Exception $e) {
                $stats['errors']++;
                Log::error("Failed to reconcile funding record {$record->reference}", [
                    'funding_record_id' => $record->id,
                    'reference' => $record->reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Reconcile a single funding record.
     *
     * @return 'credited'|'failed'|'unchanged'
     */
    public function reconcileSingleRecord(FundingRecord $record): string
    {
        if ($record->status !== FundingStatus::PENDING) {
            return 'unchanged';
        }

        $queryRef = $record->provider_reference ?: $record->reference;
        $res = $this->finityPayService->verifyPayment($queryRef);

        if ($res['status'] !== 'success') {
            // If the checkout session is older than 24 hours and unfulfilled, expire it
            if ($record->created_at <= now()->subHours(24)) {
                $record->update([
                    'status' => FundingStatus::FAILED,
                    'metadata' => array_merge($record->metadata ?? [], [
                        'reconciliation_reason' => 'Expired pending session after 24 hours.',
                    ]),
                ]);

                return 'failed';
            }

            return 'unchanged';
        }

        /** @var array<string, mixed> $data */
        $data = $res['data'] ?? [];
        $paymentStatus = strtolower((string) ($data['status'] ?? ''));

        if ($paymentStatus === 'success' || $paymentStatus === 'paid' || $paymentStatus === 'successful') {
            // Lock row and verify still pending before crediting
            DB::transaction(function () use ($record, $data): void {
                /** @var FundingRecord $lockedRecord */
                $lockedRecord = FundingRecord::where('id', $record->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedRecord->status === FundingStatus::SUCCESSFUL) {
                    return;
                }

                // Verify amount from gateway
                $amountNgn = isset($data['amount']) ? (float) $data['amount'] : null;
                $amountInKobo = $amountNgn !== null ? (int) round($amountNgn * 100) : $lockedRecord->amount;

                $wallet = $lockedRecord->wallet;
                $this->walletService->credit(
                    wallet: $wallet,
                    amount: $amountInKobo,
                    type: LedgerEntryType::FUNDING,
                    description: "Wallet Funding via FinityPay [Ref: {$lockedRecord->reference}]",
                    reference: $lockedRecord->reference,
                    metadata: [
                        'provider' => 'finitypay',
                        'reconciled' => true,
                        'gateway_reference' => $data['reference'] ?? null,
                    ]
                );

                $lockedRecord->update([
                    'status' => FundingStatus::SUCCESSFUL,
                    'provider_reference' => $data['provider_reference'] ?? $data['reference'] ?? $lockedRecord->provider_reference,
                    'processed_at' => now(),
                ]);

                $lockedRecord->customer->notify(new WalletFundedNotification(
                    amountInKobo: $amountInKobo,
                    currency: $lockedRecord->currency,
                    reference: $lockedRecord->reference
                ));
            });

            $this->auditLogger->log(
                event: AuditEventName::FUNDING_RECONCILED,
                subject: $record,
                metadata: [
                    'reference' => $record->reference,
                    'outcome' => 'credited',
                    'amount' => $record->amount,
                ]
            );

            return 'credited';
        }

        if (in_array($paymentStatus, ['failed', 'cancelled', 'reversed', 'expired'], true)) {
            $record->update([
                'status' => FundingStatus::FAILED,
                'metadata' => array_merge($record->metadata ?? [], [
                    'reconciliation_reason' => "Gateway reported {$paymentStatus}",
                ]),
                'processed_at' => now(),
            ]);

            return 'failed';
        }

        return 'unchanged';
    }
}
