<?php

use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config(['auth.device_verification_enabled' => false]);
});

it('requires authentication for wallet endpoints', function (): void {
    $this->getJson('/api/v1/wallet')->assertStatus(401);
    $this->getJson('/api/v1/wallet/transactions')->assertStatus(401);
});

it('retrieves the authenticated customer wallet and auto-provisions if none exists per Roadmap B13.1', function (): void {
    $customer = Customer::factory()->create();

    expect($customer->wallet)->toBeNull();

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'currency',
                'balance',
                'balance_formatted',
                'created_at',
                'updated_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'currency' => 'NGN',
                'balance' => 0,
                'balance_formatted' => '0.00',
            ],
        ]);

    expect($customer->fresh()->wallet)->not->toBeNull();
});

it('lists paginated transactions scoped strictly to the authenticated customer per Roadmap B13.4 & Doc 14 §8', function (): void {
    $walletService = app(WalletService::class);

    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $wallet1 = $walletService->getOrCreateWallet($customer1);
    $wallet2 = $walletService->getOrCreateWallet($customer2);

    // Create transactions for customer 1
    $walletService->credit($wallet1, 50000, LedgerEntryType::FUNDING, 'FinityPay Deposit', 'ref_cust1_fund');
    $walletService->debit($wallet1, 15000, LedgerEntryType::ORDER_DEBIT, 'Airtime purchase', 'ref_cust1_order');

    // Create transaction for customer 2
    $walletService->credit($wallet2, 100000, LedgerEntryType::FUNDING, 'Customer 2 Deposit', 'ref_cust2_fund');

    $response = $this->actingAs($customer1, 'customer')
        ->getJson('/api/v1/wallet/transactions');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'direction',
                    'type',
                    'amount',
                    'amount_formatted',
                    'balance_after',
                    'balance_after_formatted',
                    'currency',
                    'reference',
                    'description',
                    'metadata',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

    // Customer 1 sees only their 2 transactions
    expect($response->json('meta.total'))->toBe(2);

    $items = $response->json('data');
    expect($items[0]['reference'])->toBe('ref_cust1_order')
        ->and($items[1]['reference'])->toBe('ref_cust1_fund');

    // Customer 2 sees only their 1 transaction
    $response2 = $this->actingAs($customer2, 'customer')
        ->getJson('/api/v1/wallet/transactions');

    $response2->assertOk();
    expect($response2->json('meta.total'))->toBe(1)
        ->and($response2->json('data.0.reference'))->toBe('ref_cust2_fund');
});

it('filters transaction history by direction and type per Roadmap B13.4', function (): void {
    $walletService = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $walletService->getOrCreateWallet($customer);

    $walletService->credit($wallet, 50000, LedgerEntryType::FUNDING, 'Deposit 1');
    $walletService->debit($wallet, 10000, LedgerEntryType::ORDER_DEBIT, 'Order 1');
    $walletService->credit($wallet, 5000, LedgerEntryType::REFUND, 'Refund 1');

    // Filter direction=credit (2 entries)
    $creditRes = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/transactions?direction=credit');

    $creditRes->assertOk();
    expect($creditRes->json('meta.total'))->toBe(2);

    // Filter direction=debit (1 entry)
    $debitRes = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/transactions?direction=debit');

    $debitRes->assertOk();
    expect($debitRes->json('meta.total'))->toBe(1)
        ->and($debitRes->json('data.0.type'))->toBe('order_debit');

    // Filter type=refund (1 entry)
    $refundRes = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/transactions?type=refund');

    $refundRes->assertOk();
    expect($refundRes->json('meta.total'))->toBe(1)
        ->and($refundRes->json('data.0.type'))->toBe('refund');
});

it('searches transaction history by reference and description per Roadmap B13.4', function (): void {
    $walletService = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $walletService->getOrCreateWallet($customer);

    $walletService->credit($wallet, 50000, LedgerEntryType::FUNDING, 'FinityPay Card Funding', 'fin_tx_998877');
    $walletService->debit($wallet, 15000, LedgerEntryType::ORDER_DEBIT, 'MTN Data 5GB', 'order_mtn_5544');

    // Search by reference "998877"
    $refSearch = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/transactions?search=998877');

    $refSearch->assertOk();
    expect($refSearch->json('meta.total'))->toBe(1)
        ->and($refSearch->json('data.0.reference'))->toBe('fin_tx_998877');

    // Search by description "MTN"
    $descSearch = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/transactions?search=MTN');

    $descSearch->assertOk();
    expect($descSearch->json('meta.total'))->toBe(1)
        ->and($descSearch->json('data.0.description'))->toBe('MTN Data 5GB');
});
