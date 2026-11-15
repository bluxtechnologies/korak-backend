<?php

use App\Enums\FundingStatus;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Models\FundingWebhookEvent;
use App\Notifications\WalletFundedNotification;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.finitypay.secret_key' => 'sk_test_secret_123',
        'services.finitypay.webhook_secret' => 'whsec_test_secret_456',
        'services.finitypay.skip_webhook_verify' => false,
        'services.finitypay.base_url' => 'https://api.cofinance.ng/v1',
    ]);
});

it('verifies valid HMAC SHA256 webhook signatures across formats per Doc 14 §4', function (): void {
    $service = app(FinityPayService::class);
    $payload = json_encode(['event' => 'payment.success', 'data' => ['amount' => 5000]]);
    $secret = 'whsec_test_secret_456';

    // 1. Direct hex signature
    $hexSignature = hash_hmac('sha256', $payload, $secret);
    expect($service->verifyWebhookSignature($payload, $hexSignature))->toBeTrue();

    // 2. Direct base64 signature
    $b64Signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
    expect($service->verifyWebhookSignature($payload, $b64Signature))->toBeTrue();

    // 3. Timestamped signature (t=...,v1=...)
    $timestamp = (string) time();
    $timestampedPayload = $timestamp.'.'.$payload;
    $timestampedHex = hash_hmac('sha256', $timestampedPayload, $secret);
    $timestampedHeader = "t={$timestamp},v1={$timestampedHex}";
    expect($service->verifyWebhookSignature($payload, $timestampedHeader))->toBeTrue();

    // 4. Invalid signature returns false
    expect($service->verifyWebhookSignature($payload, 'invalid_signature_hash'))->toBeFalse();
});

it('initiates payment request to FinityPay gateway per Doc 15 §4', function (): void {
    Http::fake([
        'https://api.cofinance.ng/v1/payments' => Http::response([
            'status' => 'success',
            'data' => [
                'reference' => 'KORAK-FUND-TEST123',
                'checkout_url' => 'https://checkout.cofinance.ng/pay/test123',
            ],
        ], 200),
    ]);

    $service = app(FinityPayService::class);
    $customer = Customer::factory()->create([
        'username' => 'payer_user',
        'email' => 'payer@example.com',
    ]);

    $result = $service->initiatePayment(
        customer: $customer,
        amountInKobo: 50000,
        reference: 'KORAK-FUND-TEST123',
        redirectUrl: 'http://localhost:3000/wallet'
    );

    expect($result['status'])->toBe('success')
        ->and($result['reference'])->toBe('KORAK-FUND-TEST123')
        ->and($result['checkout_url'])->toBe('https://checkout.cofinance.ng/pay/test123');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.cofinance.ng/v1/payments'
            && $request['amount'] == 500.00
            && $request['customer_email'] === 'payer@example.com'
            && $request['reference'] === 'KORAK-FUND-TEST123';
    });
});

it('processes webhook notification idempotently and credits customer wallet per Roadmap B14.3 & B14.4', function (): void {
    Notification::fake();

    $walletService = app(WalletService::class);
    $service = app(FinityPayService::class);

    $customer = Customer::factory()->create(['email' => 'webhook_user@example.com']);
    $wallet = $walletService->getOrCreateWallet($customer);

    $fundingRecord = FundingRecord::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'reference' => 'KORAK-FUND-WHTEST',
        'amount' => 75000, // 750.00 NGN
        'currency' => 'NGN',
        'status' => FundingStatus::PENDING,
    ]);

    $webhookPayload = [
        'id' => 'evt_test_123',
        'event' => 'payment.success',
        'data' => [
            'reference' => 'KORAK-FUND-WHTEST',
            'amount' => 750.00,
            'customer_email' => 'webhook_user@example.com',
            'sender_name' => 'John Doe',
        ],
    ];

    // First call processes payment and credits wallet
    $firstRun = $service->processWebhook($webhookPayload, 'evt_test_123');
    expect($firstRun)->toBeTrue();

    // Verify wallet credited
    expect($wallet->fresh()->balance)->toBe(75000)
        ->and($fundingRecord->fresh()->status)->toBe(FundingStatus::SUCCESSFUL)
        ->and($fundingRecord->fresh()->processed_at)->not->toBeNull();

    // Verify notification was sent to customer
    Notification::assertSentTo($customer, WalletFundedNotification::class);

    // Second webhook delivery with identical event reference is idempotent
    $secondRun = $service->processWebhook($webhookPayload, 'evt_test_123');
    expect($secondRun)->toBeTrue();

    // Wallet balance is NOT double-credited
    expect($wallet->fresh()->balance)->toBe(75000)
        ->and(FundingWebhookEvent::where('event_reference', 'evt_test_123')->count())->toBe(1);
});
