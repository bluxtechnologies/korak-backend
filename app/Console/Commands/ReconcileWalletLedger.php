<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileWalletLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile all wallet balances against append-only ledger entries per Doc 14 §7';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): int
    {
        $this->info('Starting wallet and ledger reconciliation...');

        $wallets = Wallet::all();
        $mismatches = 0;

        foreach ($wallets as $wallet) {
            $consistent = $walletService->verifyConsistency($wallet);

            if (! $consistent) {
                $mismatches++;
                $msg = "Wallet reconciliation mismatch on Wallet ID {$wallet->id} (ULID: {$wallet->ulid}). Balance: {$wallet->balance}";
                $this->error($msg);
                Log::critical($msg, [
                    'wallet_id' => $wallet->id,
                    'ulid' => $wallet->ulid,
                    'balance' => $wallet->balance,
                ]);
            }
        }

        if ($mismatches > 0) {
            $this->error("Reconciliation completed with {$mismatches} mismatch(es) detected!");

            return Command::FAILURE;
        }

        $this->info('Reconciliation completed successfully. All wallet balances match ledger sums perfectly.');

        return Command::SUCCESS;
    }
}
