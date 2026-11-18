<?php

use App\Enums\AuditEventName;
use App\Enums\FundingStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\AuditEvent;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Models\LedgerEntry;
use App\Notifications\WalletFundedNotification;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.base_url' => 'https://api.cofinance.ng/v1',
    ]);
});

it('reconciles processing bill order to completed when provider confirms success', function (): void {
    $customer = Customer::factory()->create();

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-REC-SUCCESS',
        'category' => 'data',
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Mobile Data',
        'customer_identifier' => '08012345678',
        'amount' => 75000,
        'face_amount' => 75000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'processing',
    ]);
    $billOrder->timestamps = false;
    $billOrder->created_at = now()->subMinutes(10);
    $billOrder->save();

    Http::fake([
        'https://api.cofinance.ng/v1/bills/BIL-REC-SUCCESS' => Http::response([
            'status' => 'success',
            'message' => 'Bill details retrieved.',
            'data' => [
                'status' => 'success',
                'provider_reference' => 'X9PROV-REF',
                'token' => 'DATA-TOKEN-12345',
            ],
        ], 200),
    ]);

    $this->artisan('bills:reconcile')
        ->assertSuccessful();

    $billOrder->refresh();
    expect($billOrder->status)->toBe('completed')
        ->and($billOrder->provider_reference)->toBe('X9PROV-REF')
        ->and($billOrder->token)->toBe('DATA-TOKEN-12345')
        ->and($billOrder->processed_at)->not->toBeNull();

    // Verify audit event logged
    $audit = AuditEvent::where('event', AuditEventName::BILL_RECONCILED)
        ->where('subject_id', $billOrder->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['outcome'])->toBe('completed');
});

it('reconciles processing bill order to failed and auto-refunds wallet when provider reports failure', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    // Initial credit and debit for the order
    app(WalletService::class)->credit(
        wallet: $wallet,
        amount: 50000,
        type: LedgerEntryType::MANUAL_ADJUSTMENT,
        description: 'Initial funding',
        reference: 'INIT-REC-001'
    );

    app(WalletService::class)->debit(
        wallet: $wallet,
        amount: 50000,
        type: LedgerEntryType::ORDER_DEBIT,
        description: 'Order purchase',
        reference: 'BIL-REC-FAILED'
    );

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-REC-FAILED',
        'category' => 'airtime',
        'service_code' => 'MTN_AIRTIME',
        'service_name' => 'MTN Airtime',
        'customer_identifier' => '08098765432',
        'amount' => 50000,
        'face_amount' => 50000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'processing',
    ]);
    $billOrder->timestamps = false;
    $billOrder->created_at = now()->subMinutes(15);
    $billOrder->save();

    Http::fake([
        'https://api.cofinance.ng/v1/bills/BIL-REC-FAILED' => Http::response([
            'status' => 'error',
            'message' => 'Network route down at telecom operator.',
            'data' => [
                'status' => 'failed',
            ],
        ], 200),
    ]);

    $this->artisan('bills:reconcile')
        ->assertSuccessful();

    $billOrder->refresh();
    expect($billOrder->status)->toBe('failed')
        ->and($billOrder->metadata['reconciled_error'])->toBe('Network route down at telecom operator.');

    // Verify auto-refund credited wallet
    expect($wallet->fresh()->balance)->toBe(50000);

    $refundEntry = LedgerEntry::where('wallet_id', $wallet->id)
        ->where('reference', 'REFUND-BIL-REC-FAILED')
        ->first();
    expect($refundEntry)->not->toBeNull()
        ->and($refundEntry->type)->toBe(LedgerEntryType::REFUND)
        ->and($refundEntry->direction)->toBe(LedgerDirection::CREDIT)
        ->and($refundEntry->amount)->toBe(50000);
});

it('auto-refunds unresolved bill order older than 24 hours', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-OLD-TIMEOUT',
        'category' => 'data',
        'service_code' => 'GLO_DATA',
        'service_name' => 'Glo Data',
        'customer_identifier' => '08055555555',
        'amount' => 100000,
        'face_amount' => 100000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'processing',
    ]);
    $billOrder->timestamps = false;
    $billOrder->created_at = now()->subHours(25);
    $billOrder->save();

    // Provider returns still pending
    Http::fake([
        'https://api.cofinance.ng/v1/bills/BIL-OLD-TIMEOUT' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'pending',
            ],
        ], 200),
    ]);

    $this->artisan('bills:reconcile')
        ->assertSuccessful();

    $billOrder->refresh();
    expect($billOrder->status)->toBe('failed');
    expect($wallet->fresh()->balance)->toBe(100000);
});

it('reconciles a single bill order via artisan bills:reconcile --reference', function (): void {
    $customer = Customer::factory()->create();

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-SINGLE-01',
        'category' => 'data',
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Data',
        'customer_identifier' => '08011111111',
        'amount' => 20000,
        'face_amount' => 20000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'pending',
    ]);

    Http::fake([
        'https://api.cofinance.ng/v1/bills/BIL-SINGLE-01' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'success',
                'provider_reference' => 'PROV-SINGLE-123',
            ],
        ], 200),
    ]);

    $this->artisan('bills:reconcile', ['--reference' => 'BIL-SINGLE-01'])
        ->expectsOutputToContain('Outcome for [BIL-SINGLE-01]: completed')
        ->assertSuccessful();

    expect($billOrder->fresh()->status)->toBe('completed');
});

it('reconciles pending funding record and credits customer wallet when gateway confirms payment', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $fundingRecord = FundingRecord::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-REC01',
        'amount' => 150000, // ₦1,500.00
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);
    $fundingRecord->timestamps = false;
    $fundingRecord->created_at = now()->subMinutes(10);
    $fundingRecord->save();

    Http::fake([
        'https://api.cofinance.ng/v1/payments/verify/KORAK-FUND-REC01' => Http::response([
            'status' => 'success',
            'message' => 'Payment verified successfully.',
            'data' => [
                'status' => 'success',
                'amount' => 1500.00,
                'reference' => 'PROV-TXN-REC01',
            ],
        ], 200),
    ]);

    $this->artisan('wallet:reconcile-funding')
        ->assertSuccessful();

    $fundingRecord->refresh();
    expect($fundingRecord->status)->toBe(FundingStatus::SUCCESSFUL)
        ->and($fundingRecord->provider_reference)->toBe('PROV-TXN-REC01')
        ->and($wallet->fresh()->balance)->toBe(150000);

    Notification::assertSentTo($customer, WalletFundedNotification::class);
});

it('reconciles pending funding record to failed when gateway reports failure', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $fundingRecord = FundingRecord::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-REC-FAIL',
        'amount' => 200000,
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);
    $fundingRecord->timestamps = false;
    $fundingRecord->created_at = now()->subMinutes(12);
    $fundingRecord->save();

    Http::fake([
        'https://api.cofinance.ng/v1/payments/verify/KORAK-FUND-REC-FAIL' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'cancelled',
            ],
        ], 200),
    ]);

    $this->artisan('wallet:reconcile-funding')
        ->assertSuccessful();

    expect($fundingRecord->fresh()->status)->toBe(FundingStatus::FAILED);
    expect($wallet->fresh()->balance)->toBe(0);
});

it('reconciles single funding record via artisan wallet:reconcile-funding --reference', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $fundingRecord = FundingRecord::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-SINGLE',
        'amount' => 50000,
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);

    Http::fake([
        'https://api.cofinance.ng/v1/payments/verify/KORAK-FUND-SINGLE' => Http::response([
            'status' => 'success',
            'data' => [
                'status' => 'paid',
                'amount' => 500.00,
                'reference' => 'GATEWAY-REF-500',
            ],
        ], 200),
    ]);

    $this->artisan('wallet:reconcile-funding', ['--reference' => 'KORAK-FUND-SINGLE'])
        ->expectsOutputToContain('Outcome for [KORAK-FUND-SINGLE]: credited')
        ->assertSuccessful();

    expect($fundingRecord->fresh()->status)->toBe(FundingStatus::SUCCESSFUL);
    expect($wallet->fresh()->balance)->toBe(50000);
});

it('verifies wallet ledger integrity passing when balances match', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    app(WalletService::class)->credit(
        wallet: $wallet,
        amount: 25000,
        type: LedgerEntryType::MANUAL_ADJUSTMENT,
        description: 'Credited'
    );

    app(WalletService::class)->debit(
        wallet: $wallet,
        amount: 5000,
        type: LedgerEntryType::ORDER_DEBIT,
        description: 'Debited'
    );

    $this->artisan('wallet:reconcile')
        ->expectsOutputToContain('All wallet balances match ledger sums perfectly.')
        ->assertSuccessful();
});

it('detects and logs wallet ledger mismatch and records audit event', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    app(WalletService::class)->credit(
        wallet: $wallet,
        amount: 50000,
        type: LedgerEntryType::FUNDING,
        description: 'Legitimate credit'
    );

    // Intentionally corrupt the balance to simulate a data integrity anomaly
    $wallet->balance = 999999;
    $wallet->save();

    $this->artisan('wallet:reconcile')
        ->expectsOutputToContain('mismatch(es) detected!')
        ->assertFailed();

    // Verify critical mismatch audit event was produced
    $audit = AuditEvent::where('event', AuditEventName::WALLET_INTEGRITY_MISMATCH)
        ->where('subject_id', $wallet->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata['balance'])->toBe(999999);
});
