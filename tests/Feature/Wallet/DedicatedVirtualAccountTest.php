<?php

use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\DedicatedAccountService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.webhook_secret' => 'whsec_test_mock_webhook_secret',
        'services.finitypay.base_url' => 'https://api.finitypay.com.ng/v1',
        'services.finitypay.mock' => false,
    ]);
});

it('requires authentication to view or generate dedicated account', function (): void {
    $this->getJson('/api/v1/wallet/dedicated-account')->assertStatus(401);
    $this->postJson('/api/v1/wallet/dedicated-account')->assertStatus(401);
});

it('returns has_account: false when customer has no virtual account', function (): void {
    $customer = Customer::factory()->create(['phone' => null]);

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/dedicated-account');

    $response->assertOk()
        ->assertJson([
            'data' => null,
            'meta' => [
                'has_account' => false,
                'requires_phone' => true,
            ],
        ]);
});

it('successfully creates customer on provider and generates dedicated virtual account', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'johndoe',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '08012345678',
        'email' => 'johndoe@example.com',
    ]);

    Http::fake([
        '*/customers' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => 'cust_82a931fd',
                'name' => 'John Doe',
                'email' => 'johndoe@example.com',
                'phone' => '08012345678',
            ],
        ], 200),
        '*/virtual-accounts' => Http::response([
            'status' => 'success',
            'data' => [
                'id' => 'acc_31b9_test',
                'reference' => 'va_6a37e664f130f',
                'account_number' => '7892341201',
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'account_name' => 'COFINANCE / John Doe',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/dedicated-account');

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'bank_name' => 'Wema Bank',
                'bank_code' => '035',
                'account_number' => '7892341201',
                'account_name' => 'COFINANCE / John Doe',
                'account_reference' => 'va_6a37e664f130f',
                'status' => 'active',
            ],
            'meta' => [
                'has_account' => true,
                'created' => true,
            ],
        ]);

    expect($customer->fresh()->provider_customer_id)->toBe('cust_82a931fd');

    $virtualAccount = CustomerVirtualAccount::where('customer_id', $customer->id)->first();
    expect($virtualAccount)->not->toBeNull()
        ->and($virtualAccount->account_number)->toBe('7892341201')
        ->and($virtualAccount->bank_name)->toBe('Wema Bank');
});

it('is idempotent when calling generate dedicated account multiple times', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'johndoe',
        'email' => 'johndoe@example.com',
    ]);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    $existing = CustomerVirtualAccount::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'account_reference' => 'va_existing_123',
        'bank_name' => 'Wema Bank',
        'bank_code' => '035',
        'account_number' => '9998887771',
        'account_name' => 'COFINANCE / John Doe',
        'status' => 'active',
    ]);

    // Provider should not be called since account already exists
    Http::fake();

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/dedicated-account');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'account_number' => '9998887771',
                'bank_name' => 'Wema Bank',
            ],
            'meta' => [
                'has_account' => true,
                'already_existed' => true,
            ],
        ]);

    Http::assertNothingSent();
});

it('allows updating profile with names and phone number', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'maryjane',
        'first_name' => null,
        'last_name' => null,
        'phone' => null,
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'first_name' => 'Mary',
            'last_name' => 'Jane',
            'phone' => '+2348011223344',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'first_name' => 'Mary',
                'last_name' => 'Jane',
                'full_name' => 'Mary Jane',
                'phone' => '+2348011223344',
            ],
        ]);

    $customer->refresh();
    expect($customer->first_name)->toBe('Mary')
        ->and($customer->last_name)->toBe('Jane')
        ->and($customer->phone)->toBe('+2348011223344');
});

it('auto-credits wallet via webhook when payment arrives using account_number', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'original@example.com',
    ]);
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    CustomerVirtualAccount::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'account_reference' => 'va_match_ref',
        'bank_name' => 'Wema Bank',
        'bank_code' => '035',
        'account_number' => '1234567890',
        'account_name' => 'COFINANCE / Match User',
        'status' => 'active',
    ]);

    $payload = [
        'id' => 'evt_auto_fund_dva',
        'type' => 'wallet.funded',
        'data' => [
            'account' => [
                'account_number' => '1234567890',
                'reference' => 'va_match_ref',
                'bank_name' => 'Wema Bank',
            ],
            'transaction' => [
                'reference' => 'TXN-DVA-001',
                'amount' => 10000.00, // ₦10,000.00 = 1,000,000 kobo
                'status' => 'success',
            ],
        ],
    ];

    $rawContent = (string) json_encode($payload);
    $secret = (string) config('services.finitypay.webhook_secret');
    $signature = hash_hmac('sha256', $rawContent, $secret);

    $response = $this->call(
        method: 'POST',
        uri: '/webhooks/cofinance',
        server: [
            'HTTP_X_COFINANCE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $rawContent
    );

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    expect($wallet->fresh()->balance)->toBe(1000000); // ₦10,000.00
});

it('generates mock dedicated account when mock mode is enabled', function (): void {
    config()->set('services.finitypay.mock', true);

    $customer = Customer::factory()->create([
        'username' => 'mockuser',
        'first_name' => 'Mock',
        'last_name' => 'Tester',
        'phone' => '08099887766',
    ]);

    Http::fake();

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/dedicated-account');

    $response->assertCreated()
        ->assertJson([
            'data' => [
                'bank_name' => 'Wema Bank',
                'account_name' => 'KORAK / MOCK TESTER',
                'status' => 'active',
            ],
            'meta' => [
                'has_account' => true,
                'created' => true,
            ],
        ]);

    Http::assertNothingSent();

    $this->assertDatabaseHas('customer_virtual_accounts', [
        'customer_id' => $customer->id,
        'bank_name' => 'Wema Bank',
        'provider' => 'mock_provider',
    ]);
});

it('handles cURL or DNS connection failure cleanly with sanitized error message and 502 code', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'samuel',
        'first_name' => 'Samuel',
        'last_name' => 'Okafor',
        'phone' => '08099887711',
        'email' => 'samuel@example.com',
    ]);

    Http::fake([
        '*' => function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: api.cofinance.ng (see https://curl.se/libcurl/c/libcurl-errors.html) for https://api.cofinance.ng/v1/customers');
        },
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/dedicated-account');

    $response->assertStatus(502)
        ->assertJson([
            'status' => 'error',
            'code' => 'provider_unavailable',
            'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
        ]);

    $rawContent = (string) $response->getContent();
    expect($rawContent)->not->toContain('cURL')
        ->and($rawContent)->not->toContain('api.cofinance.ng')
        ->and($rawContent)->not->toContain('curl.se');
});

it('DedicatedAccountService catches ConnectException and returns provider_unavailable', function (): void {
    $customer = Customer::factory()->create();
    $service = app(DedicatedAccountService::class);

    Http::fake([
        '*' => function () {
            throw new ConnectionException('cURL error 6: Could not resolve host: api.cofinance.ng');
        },
    ]);

    $result = $service->generateAccount($customer);

    expect($result)->toBe([
        'status' => 'error',
        'code' => 'provider_unavailable',
        'message' => 'Payment provider is currently undergoing maintenance. Please try again shortly.',
    ]);
});

it('correctly reports isMock status on FinityPayService', function (): void {
    $service = app(FinityPayService::class);

    config()->set('services.finitypay.mock', false);
    expect($service->isMock())->toBeFalse();

    config()->set('services.finitypay.mock', true);
    expect($service->isMock())->toBeTrue();
});
