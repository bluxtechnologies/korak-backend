<?php

namespace App\Jobs;

use App\Enums\AuditEventName;
use App\Models\Wallet;
use App\Services\Audit\AuditLogger;
use App\Services\Wallet\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyWalletLedgerIntegrityJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(WalletService $walletService, AuditLogger $auditLogger): void
    {
        $wallets = Wallet::all();
        $mismatches = 0;

        foreach ($wallets as $wallet) {
            $consistent = $walletService->verifyConsistency($wallet);

            if (! $consistent) {
                $mismatches++;
                $msg = "Wallet integrity mismatch on Wallet ID {$wallet->id} (ULID: {$wallet->ulid}). Balance: {$wallet->balance}";
                Log::critical($msg, [
                    'wallet_id' => $wallet->id,
                    'ulid' => $wallet->ulid,
                    'customer_id' => $wallet->customer_id,
                    'balance' => $wallet->balance,
                ]);

                $auditLogger->log(
                    event: AuditEventName::WALLET_INTEGRITY_MISMATCH,
                    subject: $wallet,
                    metadata: [
                        'wallet_id' => $wallet->id,
                        'customer_id' => $wallet->customer_id,
                        'balance' => $wallet->balance,
                    ]
                );
            }
        }

        if ($mismatches > 0) {
            Log::warning("VerifyWalletLedgerIntegrityJob completed with {$mismatches} mismatch(es) detected!");
        } else {
            Log::info('VerifyWalletLedgerIntegrityJob completed. All wallet balances match ledger sums perfectly.');
        }
    }
}
