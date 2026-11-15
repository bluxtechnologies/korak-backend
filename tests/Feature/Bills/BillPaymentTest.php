<?php

use App\Enums\LedgerEntryType;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.base_url' => 'https://api.cofinance.ng/v1',
    ]);
});

it('fetches bill categories successfully without authentication', function (): void {
    Http::fake([
        'https://api.cofinance.ng/v1/bills/categories' => Http::response([
            'status' => 'success',
            'message' => 'Bill categories retrieved successfully.',
            'data' => [
                ['code' => 'airtime', 'name' => 'Airtime Topup'],
                ['code' => 'data', 'name' => 'Mobile Data'],
                ['code' => 'electricity', 'name' => 'Electricity Utility'],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/bills/categories');

    $response->assertOk()
        ->assertJson([
            'data' => [
                ['code' => 'airtime', 'name' => 'Airtime Topup'],
                ['code' => 'data', 'name' => 'Mobile Data'],
            ],
        ]);
});

it('fetches bill services filtered by category', function (): void {
    Http::fake([
        'https://api.cofinance.ng/v1/bills/services*' => Http::response([
            'status' => 'success',
            'message' => 'Bill services retrieved successfully.',
            'data' => [
                [
                    'service_code' => 'MTN_DATA',
                    'name' => 'MTN Mobile Data',
                    'category' => 'data',
                    'packages' => [
                        ['code' => '112381', 'name' => 'MTN 1GB Data Plan', 'amount' => 750],
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/bills/services?category=data');

    $response->assertOk()
        ->assertJson([
            'data' => [
                [
                    'service_code' => 'MTN_DATA',
                    'name' => 'MTN Mobile Data',
                ],
            ],
        ]);
});

it('validates data bundles query parameter', function (): void {
    $response = $this->getJson('/api/v1/bills/data-bundles');

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'validation_failed',
        ]);
});

it('fetches data bundles for a valid service code', function (): void {
    Http::fake([
        'https://api.cofinance.ng/v1/bills/data-bundles*' => Http::response([
            'status' => 'success',
            'message' => 'Data bundles retrieved successfully.',
            'data' => [
                ['code' => '112381', 'name' => 'MTN 1GB Data Plan (30 Days)', 'amount' => 750],
                ['code' => '112382', 'name' => 'MTN 2GB Data Plan (30 Days)', 'amount' => 1200],
            ],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/bills/data-bundles?service_code=MTN_DATA');

    $response->assertOk()
        ->assertJson([
            'data' => [
                ['code' => '112381', 'name' => 'MTN 1GB Data Plan (30 Days)'],
            ],
        ]);
});

it('requires authentication for customer validation and purchases', function (): void {
    $this->postJson('/api/v1/bills/validate', [
        'service_code' => 'IKEDC',
        'customer_identifier' => '0100234567',
    ])->assertStatus(401);

    $this->postJson('/api/v1/bills/pay', [
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Mobile Data',
        'category' => 'data',
        'customer_identifier' => '08060671945',
        'amount' => 75000,
    ])->assertStatus(401);

    $this->getJson('/api/v1/bills/history')->assertStatus(401);
    $this->getJson('/api/v1/bills/BIL-TEST-001')->assertStatus(401);
});

it('validates customer identifier for smartcards and meters', function (): void {
    $customer = Customer::factory()->create();

    Http::fake([
        'https://api.cofinance.ng/v1/bills/validate' => Http::response([
            'status' => 'success',
            'message' => 'Customer details validated successfully.',
            'data' => [
                'customer_name' => 'JOHN DOE',
                'customer_identifier' => '0100234567',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/bills/validate', [
            'service_code' => 'IKEDC',
            'customer_identifier' => '0100234567',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'customer_name' => 'JOHN DOE',
                'customer_identifier' => '0100234567',
            ],
        ]);
});

it('rejects bill payment if wallet balance is insufficient', function (): void {
    $customer = Customer::factory()->create();
    // Default wallet has 0 balance

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/bills/pay', [
            'service_code' => 'MTN_DATA',
            'service_name' => 'MTN Mobile Data',
            'category' => 'data',
            'customer_identifier' => '08060671945',
            'package_code' => '112381',
            'amount' => 75000, // 750.00 NGN
            'face_amount' => 75000,
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'insufficient_balance',
        ]);
});

it('successfully processes bill payment and debits customer wallet', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    // Fund wallet with 1,000.00 NGN (100,000 kobo)
    app(WalletService::class)->credit(
        wallet: $wallet,
        amount: 100000,
        type: LedgerEntryType::MANUAL_ADJUSTMENT,
        description: 'Test wallet initial funding',
        reference: 'TEST-FUND-001'
    );

    Http::fake([
        'https://api.cofinance.ng/v1/bills/pay' => Http::response([
            'status' => 'success',
            'message' => 'Bill payment executed successfully.',
            'data' => [
                'uuid' => 'bill_9a87f2e123',
                'service_code' => 'MTN_DATA',
                'service_name' => 'MTN Mobile Data',
                'category' => 'data',
                'customer_identifier' => '08060671945',
                'amount' => 750.0,
                'discount_amount' => 7.5,
                'charged_amount' => 742.5,
                'status' => 'success',
                'reference' => 'BIL-8F92A0E31C',
                'provider_reference' => 'X8BSN',
                'token' => 'TOKEN-1234567890',
                'created_at' => '2026-08-10T02:15:00.000000Z',
            ],
        ], 200),
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/bills/pay', [
            'service_code' => 'MTN_DATA',
            'service_name' => 'MTN Mobile Data',
            'category' => 'data',
            'customer_identifier' => '08060671945',
            'package_code' => '112381',
            'amount' => 75000, // 750.00 NGN in kobo
            'face_amount' => 75000,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'service_code' => 'MTN_DATA',
                'status' => 'completed',
                'customer_identifier' => '08060671945',
                'token' => 'TOKEN-1234567890',
            ],
        ]);

    // Wallet balance should now be 25,000 kobo (250.00 NGN)
    expect($wallet->fresh()->balance)->toBe(25000);

    // Bill order record exists in DB
    $billOrder = BillOrder::where('customer_id', $customer->id)->first();
    expect($billOrder)->not->toBeNull()
        ->and($billOrder->status)->toBe('completed')
        ->and($billOrder->token)->toBe('TOKEN-1234567890');
});

it('automatically refunds wallet if provider reports immediate failure', function (): void {
    $customer = Customer::factory()->create();
    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    // Fund wallet with 1,000.00 NGN (100,000 kobo)
    app(WalletService::class)->credit(
        wallet: $wallet,
        amount: 100000,
        type: LedgerEntryType::MANUAL_ADJUSTMENT,
        description: 'Test wallet initial funding',
        reference: 'TEST-FUND-002'
    );

    Http::fake([
        'https://api.cofinance.ng/v1/bills/pay' => Http::response([
            'status' => 'error',
            'message' => 'Provider network downtime.',
            'data' => [
                'status' => 'failed',
            ],
        ], 400),
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/bills/pay', [
            'service_code' => 'MTN_DATA',
            'service_name' => 'MTN Mobile Data',
            'category' => 'data',
            'customer_identifier' => '08060671945',
            'package_code' => '112381',
            'amount' => 75000,
            'face_amount' => 75000,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'status' => 'failed',
            ],
        ]);

    // Wallet balance should be auto-refunded back to 100,000 kobo
    expect($wallet->fresh()->balance)->toBe(100000);

    $billOrder = BillOrder::where('customer_id', $customer->id)->first();
    expect($billOrder)->not->toBeNull()
        ->and($billOrder->status)->toBe('failed');
});

it('retrieves bill purchase history scoped to the authenticated customer', function (): void {
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();

    BillOrder::create([
        'customer_id' => $customerA->id,
        'reference' => 'BIL-A1',
        'category' => 'data',
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Data',
        'customer_identifier' => '08011111111',
        'amount' => 50000,
        'face_amount' => 50000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    BillOrder::create([
        'customer_id' => $customerA->id,
        'reference' => 'BIL-A2',
        'category' => 'airtime',
        'service_code' => 'GLO_AIRTIME',
        'service_name' => 'Glo Airtime',
        'customer_identifier' => '08022222222',
        'amount' => 20000,
        'face_amount' => 20000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    BillOrder::create([
        'customer_id' => $customerB->id,
        'reference' => 'BIL-B1',
        'category' => 'electricity',
        'service_code' => 'IKEDC',
        'service_name' => 'Ikeja Electric',
        'customer_identifier' => '0100999999',
        'amount' => 100000,
        'face_amount' => 100000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    $response = $this->actingAs($customerA, 'customer')
        ->getJson('/api/v1/bills/history');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJson([
            'data' => [
                ['reference' => 'BIL-A2'],
                ['reference' => 'BIL-A1'],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 2,
            ],
        ]);
});

it('retrieves single bill purchase details by reference or ulid and forbids other customers', function (): void {
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();

    $billOrderA = BillOrder::create([
        'customer_id' => $customerA->id,
        'reference' => 'BIL-REF-A01',
        'category' => 'data',
        'service_code' => 'MTN_DATA',
        'service_name' => 'MTN Data',
        'customer_identifier' => '08011111111',
        'amount' => 50000,
        'face_amount' => 50000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
        'token' => 'DATA-TOKEN-99',
    ]);

    // Customer A can view their own bill
    $response = $this->actingAs($customerA, 'customer')
        ->getJson("/api/v1/bills/{$billOrderA->reference}");

    $response->assertOk()
        ->assertJson([
            'data' => [
                'reference' => 'BIL-REF-A01',
                'service_code' => 'MTN_DATA',
                'status' => 'completed',
                'token' => 'DATA-TOKEN-99',
            ],
        ]);

    // Customer A can also view using ULID
    $responseUlid = $this->actingAs($customerA, 'customer')
        ->getJson("/api/v1/bills/{$billOrderA->ulid}");

    $responseUlid->assertOk()
        ->assertJson([
            'data' => [
                'reference' => 'BIL-REF-A01',
            ],
        ]);

    // Customer B receives 404 when querying Customer A's bill order
    $responseB = $this->actingAs($customerB, 'customer')
        ->getJson("/api/v1/bills/{$billOrderA->reference}");

    $responseB->assertStatus(404)
        ->assertJson([
            'code' => 'bill_not_found',
        ]);
});
