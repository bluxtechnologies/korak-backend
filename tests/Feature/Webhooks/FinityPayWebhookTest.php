<?php

use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
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

it('processes live payment.received webhook with HMAC SHA256 and credits dedicated virtual account wallet', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'username' => 'obaseki',
        'email' => 'collinsobaseki23@gmail.com',
    ]);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    CustomerVirtualAccount::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'account_number' => '1299843895',
        'account_reference' => 'MPVAT-P_20261125091839338_1265181300_DYH8V_gn8w6',
        'bank_name' => 'Paga',
    ]);

    $payload = [
        'id' => '8e5d7d24-5853-42d5-ad2c-97aadd1378ad',
        'type' => 'payment.received',
        'created_at' => '2026-11-25T08:18:56.670275Z',
        'data' => [
            'merchant' => [
                'id' => '3241f123-f2c3-49e2-b880-8b788ff9733f',
                'business_name' => 'Korak Digitals',
            ],
            'customer' => [
                'uuid' => '950cbf0d-87b9-43f7-ad58-b6ac675abde2',
                'name' => 'Obaseki Collins',
                'email' => 'collinsobaseki23@gmail.com',
                'phone' => '09017063088',
            ],
            'account' => [
                'id' => 'c09181a9-ba54-4f8e-9205-53553c5189ee',
                'reference' => 'MPVAT-P_20261125091839338_1265181300_DYH8V_gn8w6',
                'account_number' => '1299843895',
                'bank_name' => 'Paga',
                'bank_code' => '100002',
                'account_name' => 'Korak/ObasekiCollins',
            ],
            'transaction' => [
                'id' => '2581a0e5-caab-44dc-8cbe-955c00eb3bf5',
                'reference' => 'tx_3180ec26-93d0-434f-91ca-91ca819ce638',
                'amount' => 100,
                'fee' => 10,
                'net_amount' => 90,
                'currency' => 'NGN',
                'status' => 'success',
                'side' => 'credit',
                'channel' => 'bank_transfer',
                'settled_at' => '2026-11-25T08:18:53.000000Z',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $sha256Signature = hash_hmac('sha256', $rawContent, $secret);

    // Call webhook with X-FinityPay-Signature header
    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_FINITYPAY_SIGNATURE' => $sha256Signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    // Customer wallet is credited 100 NGN (10,000 kobo)
    expect($wallet->fresh()->balance)->toBe(10000);
});

it('verifies webhook signature matching against cofinance_webhook_secret fallback when configured', function (): void {
    Notification::fake();

    config([
        'services.finitypay.webhook_secret' => 'primary_secret',
        'services.finitypay.cofinance_webhook_secret' => 'alternate_cofinance_secret',
    ]);

    $customer = Customer::factory()->create(['email' => 'altsecret@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $payload = [
        'id' => 'evt_alt_secret_test',
        'type' => 'payment.received',
        'data' => [
            'customer' => ['email' => 'altsecret@example.com'],
            'transaction' => [
                'reference' => 'TXN-ALT-001',
                'amount' => 500,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    // Sign with alternate secret
    $signature = hash_hmac('sha256', $rawContent, 'alternate_cofinance_secret');

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk();
    expect($wallet->fresh()->balance)->toBe(50000);
});

it('verifies standard svix webhook signature using whsec_ prefix and base64 decoded key with msgId and timestamp', function (): void {
    Notification::fake();

    $secret = 'whsec_UDkSqa5IJ9PqzLiEKvNf8QA79VCDYgXWVwylxZ73';
    config([
        'services.finitypay.webhook_secret' => $secret,
    ]);

    $customer = Customer::factory()->create(['email' => 'svix@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $msgId = 'msg_test_svix_001';
    $timestamp = (string) time();

    $payload = [
        'id' => $msgId,
        'type' => 'payment.received',
        'data' => [
            'customer' => ['email' => 'svix@example.com'],
            'transaction' => [
                'reference' => 'TXN-SVIX-001',
                'amount' => 750,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);

    // Official Svix specification:
    // 1. key = base64_decode(substr(secret, 6))
    // 2. signedContent = "${msgId}.${timestamp}.${rawContent}"
    // 3. signature = "v1," . base64_encode(hash_hmac('sha256', $signedContent, $key, true))
    $keyBytes = (string) base64_decode(substr($secret, 6), true);
    $signedContent = "{$msgId}.{$timestamp}.{$rawContent}";
    $b64Sig = base64_encode(hash_hmac('sha256', $signedContent, $keyBytes, true));
    $svixHeader = "v1,{$b64Sig}";

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_WEBHOOK_ID' => $msgId,
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $svixHeader,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    expect($wallet->fresh()->balance)->toBe(75000); // 750 NGN = 75,000 kobo
});

it('verifies webhook signature when FinityPay sends space-delimited svix signatures or svix headers', function (): void {
    Notification::fake();

    $secret = 'whsec_UDkSqa5IJ9PqzLiEKvNf8QA79VCDYgXWVwylxZ73';
    config([
        'services.finitypay.webhook_secret' => $secret,
    ]);

    $customer = Customer::factory()->create(['email' => 'svix2@example.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $msgId = '8e5d7d24-5853-42d5-ad2c-97aadd1378ad';
    $timestamp = '1732522736';

    $payload = [
        'id' => $msgId,
        'type' => 'payment.received',
        'data' => [
            'customer' => ['email' => 'svix2@example.com'],
            'transaction' => [
                'reference' => 'TXN-SVIX-002',
                'amount' => 100,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $keyBytes = (string) base64_decode(substr($secret, 6), true);
    $signedContent = "{$msgId}.{$timestamp}.{$rawContent}";
    $sig1 = base64_encode(hash_hmac('sha256', $signedContent, $keyBytes, true));

    // Space-delimited signatures with rotated or dummy signature
    $headerVal = "v1,dummySignature v1,{$sig1}";

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_SVIX_ID' => $msgId,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => $headerVal,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk();
    expect($wallet->fresh()->balance)->toBe(10000);
});

it('verifies live payment.received webhook with X-CoFinance-Signature t=...,v1=... and X-CoFinance-Event-Id header', function (): void {
    Notification::fake();

    $secret = 'whsec_UDkSqa5IJ9PqzLiEKvNf8QA79VCDYgXWVwylxZ73';
    config([
        'services.finitypay.webhook_secret' => $secret,
    ]);

    $customer = Customer::factory()->create(['email' => 'frankutoo518@gmail.com']);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $eventId = '216aad7a-6164-4ecd-8a96-8cbd939668aa';
    $timestamp = '1795639719';

    $payload = [
        'id' => $eventId,
        'type' => 'payment.received',
        'created_at' => '2026-11-25T18:24:27.718897Z',
        'data' => [
            'customer' => [
                'email' => 'frankutoo518@gmail.com',
                'name' => 'Deborah Utoo',
            ],
            'account' => [
                'account_number' => '3138435373',
                'bank_name' => 'Paga',
            ],
            'transaction' => [
                'reference' => 'tx_e9e208ab-8eed-4ae2-8988-d009bcf4afa7',
                'amount' => 500,
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);

    // Test with stripped secret and timestamped format t={ts}.{body}
    $toSign = "{$timestamp}.{$rawContent}";
    $b64Sig = base64_encode(hash_hmac('sha256', $toSign, substr($secret, 6), true));
    $sigHeader = "t={$timestamp},v1={$b64Sig}";

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_EVENT' => 'payment.received',
            'HTTP_X_COFINANCE_EVENT_ID' => $eventId,
            'HTTP_X_COFINANCE_TIMESTAMP' => $timestamp,
            'HTTP_X_COFINANCE_SIGNATURE' => $sigHeader,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);

    expect($wallet->fresh()->balance)->toBe(50000); // 500 NGN = 50,000 kobo
});

it('responds to GET request on webhook endpoint with health status and version', function (): void {
    $response = $this->get('/webhooks/cofinance');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'version' => '2026.11.25.v4',
        ]);
});
