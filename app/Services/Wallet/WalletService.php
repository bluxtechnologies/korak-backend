<?php

namespace App\Services\Wallet;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    /**
     * Get or create the customer's primary wallet.
     */
    public function getOrCreateWallet(Customer $customer, string $currency = 'NGN'): Wallet
    {
        return Wallet::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'currency' => $currency,
                'balance' => 0,
            ]
        );
    }

    /**
     * Credit funds to a wallet.
     *
     * Per Doc 14 §1 & §2:
     * - Runs inside a single database transaction with a row lock.
     * - Idempotent by reference.
     * - Creates an append-only ledger record and updates balance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function credit(
        Wallet $wallet,
        int $amount,
        LedgerEntryType $type,
        string $description,
        ?string $reference = null,
        array $metadata = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $reference, $metadata): LedgerEntry {
            // Check idempotency if a reference is provided
            if ($reference !== null) {
                /** @var LedgerEntry|null $existing */
                $existing = LedgerEntry::where('wallet_id', $wallet->id)
                    ->where('reference', $reference)
                    ->where('direction', LedgerDirection::CREDIT)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            // Lock wallet row for update
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newBalance = $lockedWallet->balance + $amount;

            $lockedWallet->balance = $newBalance;
            $lockedWallet->save();

            return LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'direction' => LedgerDirection::CREDIT,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'currency' => $lockedWallet->currency,
                'reference' => $reference,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Debit funds from a wallet.
     *
     * Per Doc 14 §1 & §2:
     * - Runs inside a single database transaction with a row lock.
     * - Enforces strict no-overdraw: throws InsufficientBalanceException if balance < amount.
     * - Idempotent by reference.
     * - Creates an append-only ledger record and updates balance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function debit(
        Wallet $wallet,
        int $amount,
        LedgerEntryType $type,
        string $description,
        ?string $reference = null,
        array $metadata = []
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $reference, $metadata): LedgerEntry {
            // Check idempotency if a reference is provided
            if ($reference !== null) {
                /** @var LedgerEntry|null $existing */
                $existing = LedgerEntry::where('wallet_id', $wallet->id)
                    ->where('reference', $reference)
                    ->where('direction', LedgerDirection::DEBIT)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            // Lock wallet row for update
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWallet->balance < $amount) {
                throw new InsufficientBalanceException('Insufficient wallet balance.');
            }

            $newBalance = $lockedWallet->balance - $amount;

            $lockedWallet->balance = $newBalance;
            $lockedWallet->save();

            return LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'direction' => LedgerDirection::DEBIT,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'currency' => $lockedWallet->currency,
                'reference' => $reference,
                'description' => $description,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Verify consistency between wallet balance and total ledger sum per Doc 14 §7.
     */
    public function verifyConsistency(Wallet $wallet): bool
    {
        $credits = (int) LedgerEntry::where('wallet_id', $wallet->id)
            ->where('direction', LedgerDirection::CREDIT)
            ->sum('amount');

        $debits = (int) LedgerEntry::where('wallet_id', $wallet->id)
            ->where('direction', LedgerDirection::DEBIT)
            ->sum('amount');

        $expectedBalance = $credits - $debits;

        return $wallet->fresh()->balance === $expectedBalance;
    }
}
