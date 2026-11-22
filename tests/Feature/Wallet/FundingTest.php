<?php

use App\Enums\FundingStatus;
use App\Enums\LedgerEntryType;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Notifications\WalletFundedNotification;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config([
        'auth.device_verification_enabled' => false,
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.webhook_secret' => 'whsec_test_secret',
        'services.finitypay.skip_webhook_verify' => false,
        'services.finitypay.base_url' => 'https://api.finitypay.com.ng/v1',
    ]);
});

it('requires authentication to initiate wallet funding', function (): void {
    $this->postJson('/api/v1/wallet/fund', ['amount' => 50000])->assertStatus(401);
    $this->getJson('/api/v1/wallet/fund/KORAK-FUND-123')->assertStatus(401);
});

it('validates minimum funding amount of 100 NGN (10000 kobo) per Doc 14', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/fund', [
            'amount' => 5000, // 50 NGN - below 100 NGN minimum
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('initiates wallet funding session and returns checkout URL per Roadmap B14.1', function (): void {
    Http::fake([
        'https://api.finitypay.com.ng/v1/payments' => Http::response([
            'status' => 'success',
            'data' => [
                'reference' => 'KORAK-FUND-ABCXYZ',
                'checkout_url' => 'https://checkout.cofinance.ng/pay/ABCXYZ',
            ],
        ], 200),
    ]);

    $customer = Customer::factory()->create([
        'username' => 'test_funder',
        'email' => 'funder@example.com',
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/fund', [
            'amount' => 50000, // 500.00 NGN
            'redirect_url' => 'http://localhost:3000/wallet',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'reference',
                'provider',
                'amount',
                'amount_formatted',
                'currency',
                'status',
                'checkout_url',
                'created_at',
            ],
            'meta',
        ])
        ->assertJson([
            'data' => [
                'amount' => 50000,
                'amount_formatted' => '500.00',
                'currency' => 'NGN',
                'status' => 'pending',
                'checkout_url' => 'https://checkout.cofinance.ng/pay/ABCXYZ',
            ],
        ]);

    $this->assertDatabaseHas('funding_records', [
        'customer_id' => $customer->id,
        'amount' => 50000,
        'status' => 'pending',
    ]);
});

it('retrieves the status of a funding record scoped to the authenticated customer', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $wallet1 = app(WalletService::class)->getOrCreateWallet($customer1);

    $record = FundingRecord::create([
        'customer_id' => $customer1->id,
        'wallet_id' => $wallet1->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-MINE',
        'amount' => 100000,
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);

    // Customer 2 cannot view Customer 1's funding record
    $this->actingAs($customer2, 'customer')
        ->getJson("/api/v1/wallet/fund/{$record->reference}")
        ->assertStatus(404);

    // Customer 1 can view own record
    $response = $this->actingAs($customer1, 'customer')
        ->getJson("/api/v1/wallet/fund/{$record->reference}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'reference' => 'KORAK-FUND-MINE',
                'amount' => 100000,
                'status' => 'pending',
            ],
        ]);
});

it('rejects webhooks with invalid signatures per Doc 14 §4', function (): void {
    $payload = ['event' => 'payment.success', 'data' => ['reference' => 'ref_123']];

    $response = $this->withHeaders([
        'x-cofinance-signature' => 'invalid_signature_hash',
    ])->postJson('/api/v1/webhooks/finitypay', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'code' => 'invalid_webhook_signature',
        ]);
});

it('processes webhook with valid signature and credits customer wallet per Roadmap B14.3 & B14.4', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create(['email' => 'credited@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $fundingRecord = FundingRecord::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-HOOK1',
        'amount' => 50000,
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);

    $payloadArray = [
        'id' => 'evt_valid_1',
        'event' => 'payment.success',
        'data' => [
            'reference' => 'KORAK-FUND-HOOK1',
            'amount' => 500.00,
            'customer_email' => 'credited@example.com',
            'sender_name' => 'Jane Doe',
        ],
    ];

    $rawContent = json_encode($payloadArray);
    $secret = 'whsec_test_secret';
    $validSignature = hash_hmac('sha256', (string) $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/api/v1/webhooks/finitypay',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $validSignature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: (string) $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    // Wallet is credited
    expect($wallet->fresh()->balance)->toBe(50000)
        ->and($fundingRecord->fresh()->status)->toBe(FundingStatus::SUCCESSFUL);

    // Notification is dispatched
    Notification::assertSentTo($customer, WalletFundedNotification::class);
});

it('reconciles wallet balances against append-only ledger via artisan command per Roadmap B14.5', function (): void {
    $walletService = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $walletService->getOrCreateWallet($customer);

    $walletService->credit($wallet, 50000, LedgerEntryType::FUNDING, 'Deposit 1');
    $walletService->debit($wallet, 20000, LedgerEntryType::ORDER_DEBIT, 'Order 1');

    $this->artisan('wallet:reconcile')
        ->expectsOutputToContain('Reconciliation completed successfully')
        ->assertSuccessful();
});
