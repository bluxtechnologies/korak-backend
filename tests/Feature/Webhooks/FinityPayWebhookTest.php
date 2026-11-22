<?php

use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Notifications\WalletFundedNotification;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.webhook_secret' => 'whsec_test_mock_webhook_secret',
        'services.finitypay.skip_webhook_verify' => false,
        'services.finitypay.base_url' => 'https://api.finitypay.com.ng/v1',
    ]);
});

it('verifies webhook signature using HMAC SHA512 per public documentation', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create(['email' => 'sha512@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $payload = [
        'id' => 'evt_sha512_test',
        'type' => 'wallet.funded',
        'data' => [
            'customer' => ['email' => 'sha512@example.com', 'name' => 'SHA 512 User'],
            'transaction' => [
                'reference' => 'TXN-SHA512-001',
                'amount' => 5000.00,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sha512Signature = hash_hmac('sha512', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sha512Signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    expect($wallet->fresh()->balance)->toBe(500000); // 5,000.00 NGN = 500,000 kobo
});

it('verifies webhook signature using HMAC SHA256 per dashboard instructions', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create(['email' => 'sha256@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $payload = [
        'id' => 'evt_sha256_test',
        'type' => 'wallet.funded',
        'data' => [
            'customer' => ['email' => 'sha256@example.com', 'name' => 'SHA 256 User'],
            'transaction' => [
                'reference' => 'TXN-SHA256-001',
                'amount' => 2500.00,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sha256Signature = hash_hmac('sha256', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/api/v1/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sha256Signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    expect($wallet->fresh()->balance)->toBe(250000); // 2,500.00 NGN = 250,000 kobo
});

it('rejects webhooks with an invalid signature', function (): void {
    $payload = ['id' => 'evt_fake', 'type' => 'wallet.funded'];
    $rawContent = (string) json_encode($payload);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => 'invalid_hash_signature',
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertStatus(401)
        ->assertJson([
            'code' => 'invalid_webhook_signature',
        ]);
});

it('processes wallet.funded webhook event from dedicated virtual account transfer', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'username' => 'musa',
        'email' => 'musa@example.com',
    ]);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $payload = [
        'id' => 'evt_01JXMUSA',
        'type' => 'wallet.funded',
        'created_at' => '2026-06-17T15:48:42.000000Z',
        'data' => [
            'merchant' => [
                'id' => 'mer_82a931fd',
                'business_name' => 'Korak Digitals',
            ],
            'customer' => [
                'uuid' => 'cust_82a931fd',
                'name' => 'Alhaji Musa',
                'email' => 'musa@example.com',
                'phone' => '+2348011223344',
            ],
            'account' => [
                'id' => 'acc_31b9',
                'reference' => 'va_6a37e664f130f',
                'account_number' => '7892341201',
                'bank_name' => 'Wema Bank',
                'account_name' => 'COFINANCE / Alhaji Musa',
            ],
            'transaction' => [
                'id' => 'txn_8f1e',
                'reference' => 'va_6a37e664f130f',
                'provider_reference' => 'API-VTU-FUND-06AEF-21dc05a8-8e7b-4425-b188-368c23bf4d49',
                'amount' => 15000.00,
                'fee' => 10.00,
                'net_amount' => 14990.00,
                'currency' => 'NGN',
                'status' => 'success',
                'side' => 'credit',
                'channel' => 'bank_transfer',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sig = hash_hmac('sha512', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk();

    // 15,000.00 NGN in kobo = 1,500,000
    expect($wallet->fresh()->balance)->toBe(1500000);

    Notification::assertSentTo($customer, WalletFundedNotification::class);

    // Replay idempotency: second call does not duplicate credit
    $response2 = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response2->assertOk();
    expect($wallet->fresh()->balance)->toBe(1500000);
});

it('processes bill.successful webhook and updates bill order status and token', function (): void {
    $customer = Customer::factory()->create();

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-8F92A0E31C',
        'category' => 'electricity',
        'service_code' => 'IKEDC',
        'service_name' => 'Ikeja Electric',
        'customer_identifier' => '0100234567',
        'amount' => 500000,
        'face_amount' => 500000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'processing',
    ]);

    $payload = [
        'id' => 'evt_bill_success_1',
        'type' => 'bill.successful',
        'created_at' => '2026-08-10T02:40:00.000000Z',
        'data' => [
            'bill_payment' => [
                'uuid' => 'bill_9a87f2e',
                'reference' => 'BIL-8F92A0E31C',
                'provider_reference' => 'PROV-REF-12345',
                'service_code' => 'IKEDC',
                'service_name' => 'Ikeja Electric',
                'category' => 'electricity',
                'customer_identifier' => '0100234567',
                'amount' => 5000.0,
                'status' => 'success',
                'token' => '2179-3858-8123-4567',
                'created_at' => '2026-08-10T02:40:00.000000Z',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sig = hash_hmac('sha512', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk();

    $freshOrder = $billOrder->fresh();
    expect($freshOrder->status)->toBe('completed')
        ->and($freshOrder->provider_reference)->toBe('PROV-REF-12345')
        ->and($freshOrder->token)->toBe('2179-3858-8123-4567');
});

it('processes bill.failed webhook and automatically refunds customer wallet', function (): void {
    $walletService = app(WalletService::class);
    $customer = Customer::factory()->create();
    $wallet = $walletService->getOrCreateWallet($customer);

    // Initial wallet balance: 10,000 NGN (1,000,000 kobo)
    $walletService->credit($wallet, 1000000, LedgerEntryType::FUNDING, 'Initial balance');

    // Customer places order for 750 NGN (75,000 kobo) -> balance becomes 925,000 kobo
    $walletService->debit($wallet, 75000, LedgerEntryType::ORDER_DEBIT, 'Bill payment order', 'BIL-FAIL-TEST-1');

    $billOrder = BillOrder::create([
        'customer_id' => $customer->id,
        'reference' => 'BIL-FAIL-TEST-1',
        'category' => 'data',
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Mobile Data',
        'package_code' => '112381',
        'customer_identifier' => '08060671945',
        'amount' => 75000,
        'face_amount' => 75000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'processing',
    ]);

    expect($wallet->fresh()->balance)->toBe(925000);

    $payload = [
        'id' => 'evt_bill_fail_1',
        'type' => 'bill.failed',
        'created_at' => '2026-08-10T02:40:00.000000Z',
        'data' => [
            'bill_payment' => [
                'reference' => 'BIL-FAIL-TEST-1',
                'provider_reference' => 'PROV-FAIL-999',
                'service_code' => 'MTN_DATA',
                'status' => 'failed',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sig = hash_hmac('sha512', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk();

    // Bill order is refunded
    expect($billOrder->fresh()->status)->toBe('refunded');

    // Customer wallet is automatically refunded back to 1,000,000 kobo!
    expect($wallet->fresh()->balance)->toBe(1000000);
});
