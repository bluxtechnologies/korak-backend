<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates or retrieves the primary wallet for a customer', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();

    $wallet = $service->getOrCreateWallet($customer, 'NGN');

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->customer_id)->toBe($customer->id)
        ->and($wallet->currency)->toBe('NGN')
        ->and($wallet->balance)->toBe(0);

    // Second call returns existing wallet
    $sameWallet = $service->getOrCreateWallet($customer);
    expect($sameWallet->id)->toBe($wallet->id);
});

it('credits wallet, updates balance, and creates append-only ledger entry per Doc 14 §1 & §2', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $entry = $service->credit(
        wallet: $wallet,
        amount: 50000, // 500.00 NGN in kobo
        type: LedgerEntryType::FUNDING,
        description: 'Card Funding via FinityPay',
        reference: 'fin_ref_123',
        metadata: ['gateway' => 'finitypay']
    );

    expect($entry)->toBeInstanceOf(LedgerEntry::class)
        ->and($entry->direction)->toBe(LedgerDirection::CREDIT)
        ->and($entry->type)->toBe(LedgerEntryType::FUNDING)
        ->and($entry->amount)->toBe(50000)
        ->and($entry->balance_after)->toBe(50000)
        ->and($entry->reference)->toBe('fin_ref_123')
        ->and($entry->metadata['gateway'])->toBe('finitypay')
        ->and($wallet->fresh()->balance)->toBe(50000);
});

it('enforces idempotency on credit by returning existing ledger entry for identical reference', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $firstEntry = $service->credit(
        wallet: $wallet,
        amount: 25000,
        type: LedgerEntryType::FUNDING,
        description: 'Deposit',
        reference: 'unique_ref_abc'
    );

    // Second call with same reference must NOT double credit
    $secondEntry = $service->credit(
        wallet: $wallet,
        amount: 25000,
        type: LedgerEntryType::FUNDING,
        description: 'Duplicate Deposit Call',
        reference: 'unique_ref_abc'
    );

    expect($secondEntry->id)->toBe($firstEntry->id)
        ->and($wallet->fresh()->balance)->toBe(25000)
        ->and(LedgerEntry::where('wallet_id', $wallet->id)->count())->toBe(1);
});

it('debits wallet when balance is sufficient per Doc 14 §1 & §2', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $service->credit(
        wallet: $wallet,
        amount: 100000,
        type: LedgerEntryType::FUNDING,
        description: 'Initial balance'
    );

    $debitEntry = $service->debit(
        wallet: $wallet,
        amount: 30000,
        type: LedgerEntryType::ORDER_DEBIT,
        description: 'Airtime purchase',
        reference: 'order_123'
    );

    expect($debitEntry->direction)->toBe(LedgerDirection::DEBIT)
        ->and($debitEntry->amount)->toBe(30000)
        ->and($debitEntry->balance_after)->toBe(70000)
        ->and($wallet->fresh()->balance)->toBe(70000);
});

it('enforces idempotency on debit by returning existing entry for identical reference', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $service->credit($wallet, 50000, LedgerEntryType::FUNDING, 'Fund');

    $firstDebit = $service->debit($wallet, 20000, LedgerEntryType::ORDER_DEBIT, 'Order 1', 'idempotent_order_key');
    $secondDebit = $service->debit($wallet, 20000, LedgerEntryType::ORDER_DEBIT, 'Order 1 retry', 'idempotent_order_key');

    expect($secondDebit->id)->toBe($firstDebit->id)
        ->and($wallet->fresh()->balance)->toBe(30000);
});

it('rejects debit and prevents overdraw when balance is insufficient per Roadmap B13.3', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $service->credit($wallet, 10000, LedgerEntryType::FUNDING, 'Fund 100 NGN');

    expect(fn () => $service->debit($wallet, 15000, LedgerEntryType::ORDER_DEBIT, 'Excessive purchase'))
        ->toThrow(InsufficientBalanceException::class, 'Insufficient wallet balance.');

    // Balance remains exactly 10000 with no negative balance
    expect($wallet->fresh()->balance)->toBe(10000)
        ->and(LedgerEntry::where('direction', LedgerDirection::DEBIT)->count())->toBe(0);
});

it('rejects zero or negative amounts with InvalidArgumentException', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    expect(fn () => $service->credit($wallet, 0, LedgerEntryType::FUNDING, 'Zero'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->debit($wallet, -500, LedgerEntryType::ORDER_DEBIT, 'Negative'))
        ->toThrow(InvalidArgumentException::class);
});

it('verifies mathematical consistency between ledger sum and wallet balance per Doc 14 §7', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $service->credit($wallet, 50000, LedgerEntryType::FUNDING, 'Fund 1');
    $service->credit($wallet, 30000, LedgerEntryType::FUNDING, 'Fund 2');
    $service->debit($wallet, 20000, LedgerEntryType::ORDER_DEBIT, 'Purchase 1');
    $service->credit($wallet, 5000, LedgerEntryType::REFUND, 'Refund 1');

    // Total: +50,000 +30,000 -20,000 +5,000 = 65,000
    expect($service->verifyConsistency($wallet))->toBeTrue()
        ->and($wallet->fresh()->balance)->toBe(65000);
});

it('enforces append-only immutability on ledger entries per Doc 14 §1', function (): void {
    $service = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $service->getOrCreateWallet($customer);

    $entry = $service->credit($wallet, 10000, LedgerEntryType::FUNDING, 'Test');

    // Attempting to update a ledger entry must throw LogicException
    expect(fn () => $entry->update(['amount' => 99999]))
        ->toThrow(LogicException::class, 'Ledger entries are strictly append-only and cannot be updated.');

    // Attempting to delete a ledger entry must throw LogicException
    expect(fn () => $entry->delete())
        ->toThrow(LogicException::class, 'Ledger entries are strictly append-only and cannot be deleted.');
});
