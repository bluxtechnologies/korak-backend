<?php

use App\Enums\FundingStatus;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\FundingRecord;
use App\Models\Setting;
use App\Models\TransactionFeeTier;
use App\Notifications\WalletFundedNotification;
use App\Services\Wallet\TransactionFeeService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config([
        'services.finitypay.secret_key' => 'sk_live_test_secret',
        'services.finitypay.webhook_secret' => 'whsec_test_secret',
        'services.finitypay.skip_webhook_verify' => false,
        'services.finitypay.base_url' => 'https://api.finitypay.com.ng/v1',
    ]);

    // Ensure the 5 canonical fee tiers exist in the database
    TransactionFeeTier::query()->delete();

    TransactionFeeTier::create([
        'transaction_type' => 'deposit',
        'name' => 'Below ₦1,000',
        'min_amount' => 0,
        'max_amount' => 99999,
        'fee' => 2000, // ₦20.00
        'is_active' => true,
        'priority' => 1,
    ]);

    TransactionFeeTier::create([
        'transaction_type' => 'deposit',
        'name' => '₦1,000 to ₦10,000',
        'min_amount' => 100000,
        'max_amount' => 1000000,
        'fee' => 7500, // ₦75.00
        'is_active' => true,
        'priority' => 2,
    ]);

    TransactionFeeTier::create([
        'transaction_type' => 'deposit',
        'name' => '₦10,001 to ₦50,000',
        'min_amount' => 1000001,
        'max_amount' => 5000000,
        'fee' => 15500, // ₦155.00
        'is_active' => true,
        'priority' => 3,
    ]);

    TransactionFeeTier::create([
        'transaction_type' => 'deposit',
        'name' => '₦50,001 to ₦100,000',
        'min_amount' => 5000001,
        'max_amount' => 10000000,
        'fee' => 35000, // ₦350.00
        'is_active' => true,
        'priority' => 4,
    ]);

    TransactionFeeTier::create([
        'transaction_type' => 'deposit',
        'name' => 'Above ₦100,000',
        'min_amount' => 10000001,
        'max_amount' => null,
        'fee' => 50000, // ₦500.00
        'is_active' => true,
        'priority' => 5,
    ]);

    app(TransactionFeeService::class)->syncSettings();
});

it('requires customer authentication for deposit endpoints', function (): void {
    $this->getJson('/api/v1/wallet/deposit/tiers')->assertStatus(401);
    $this->postJson('/api/v1/wallet/deposit/calculate', ['amount_ngn' => 1000])->assertStatus(401);
    $this->postJson('/api/v1/wallet/deposit/initiate', ['amount_ngn' => 1000])->assertStatus(401);
});

it('returns active fee tiers and synchronized settings context', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/wallet/deposit/tiers');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tiers',
                'fee_context',
                'has_dedicated_account',
            ],
        ]);

    $data = $response->json('data');
    expect($data['tiers'])->toHaveCount(5);
    expect($data['tiers'][0]['fee_formatted'])->toBe('₦20.00');
    expect($data['tiers'][1]['fee_formatted'])->toBe('₦75.00');
    expect($data['tiers'][2]['fee_formatted'])->toBe('₦155.00');
    expect($data['tiers'][3]['fee_formatted'])->toBe('₦350.00');
    expect($data['tiers'][4]['fee_formatted'])->toBe('₦500.00');

    // Verify settings table synchronization
    $cachedSettings = Setting::getValue('deposit_fee_tiers');
    expect($cachedSettings)->toBeArray()->toHaveCount(5);
});

it('calculates deposit fees accurately across all 5 tiers', function (float $amountNgn, int $expectedFeeKobo, int $expectedTotalKobo): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/deposit/calculate', [
            'amount_ngn' => $amountNgn,
        ]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['fee_kobo'])->toBe($expectedFeeKobo)
        ->and($data['total_payable_kobo'])->toBe($expectedTotalKobo)
        ->and($data['requested_amount_kobo'])->toBe((int) round($amountNgn * 100));
})->with([
    'Below ₦1,000 (₦500)' => [500.0, 2000, 52000],
    'Tier 1 boundary (₦1,000)' => [1000.0, 7500, 107500],
    'Tier 1 middle (₦5,000)' => [5000.0, 7500, 507500],
    'Tier 1 upper boundary (₦10,000)' => [10000.0, 7500, 1007500],
    'Tier 2 lower (₦10,001)' => [10001.0, 15500, 1015600],
    'Tier 2 middle (₦25,000)' => [25000.0, 15500, 2515500],
    'Tier 2 upper boundary (₦50,000)' => [50000.0, 15500, 5015500],
    'Tier 3 middle (₦75,000)' => [75000.0, 35000, 7535000],
    'Tier 3 upper boundary (₦100,000)' => [100000.0, 35000, 10035000],
    'Tier 4 above ₦100,000 (₦250,000)' => [250000.0, 50000, 25050000],
]);

it('validates minimum deposit amount on calculation', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/deposit/calculate', [
            'amount_ngn' => 50, // ₦50 < ₦100 minimum
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'amount_too_low',
        ]);
});

it('initiates deposit intent and returns dedicated account details', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'obaseki',
        'first_name' => 'Collins',
        'last_name' => 'Obaseki',
        'phone' => '09017063088',
    ]);

    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    CustomerVirtualAccount::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'bank_name' => 'Paga',
        'bank_code' => '100002',
        'account_number' => '1299843895',
        'account_name' => 'Korak/ObasekiCollins',
        'account_reference' => 'MPVAT-P_12345',
        'status' => 'active',
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson('/api/v1/wallet/deposit/initiate', [
            'amount_ngn' => 1000,
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'reference',
                'status',
                'breakdown' => [
                    'requested_amount_kobo',
                    'fee_kobo',
                    'total_payable_kobo',
                    'fee_formatted',
                    'total_payable_formatted',
                ],
                'virtual_account' => [
                    'bank_name',
                    'account_number',
                    'account_name',
                ],
                'instructions' => [
                    'headline',
                    'steps',
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['breakdown']['requested_amount_kobo'])->toBe(100000);
    expect($data['breakdown']['fee_kobo'])->toBe(7500);
    expect($data['breakdown']['total_payable_kobo'])->toBe(107500);
    expect($data['virtual_account']['account_number'])->toBe('1299843895');
    expect($data['virtual_account']['bank_name'])->toBe('Paga');

    // Verify funding record saved in database with fee breakdown
    $record = FundingRecord::where('reference', $data['reference'])->first();
    expect($record)->not->toBeNull();
    expect($record->amount)->toBe(100000); // ₦1,000 net intent
    expect($record->gross_amount)->toBe(107500); // ₦1,075 total expected
    expect($record->fee)->toBe(7500); // ₦75 fee
});

it('automatically deducts fee from direct incoming bank transfer via webhook and credits net amount', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'username' => 'deborah',
        'first_name' => 'Deborah',
        'last_name' => 'Utoo',
    ]);

    $wallet = app(WalletService::class)->getOrCreateWallet($customer);

    CustomerVirtualAccount::create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'provider' => 'finitypay',
        'bank_name' => 'Paga',
        'bank_code' => '100002',
        'account_number' => '3138435373',
        'account_name' => 'Korak/DeborahUtoo',
        'account_reference' => 'MPVAT-P_TEST_REF',
        'status' => 'active',
    ]);

    $secret = 'whsec_test_secret';
    config([
        'services.finitypay.webhook_secret' => $secret,
        'services.finitypay.skip_webhook_verify' => false,
    ]);

    // Gross incoming transfer of ₦1,000 (1000.00 NGN)
    // Fee tier for ₦1,000 is ₦75 (7500 kobo)
    // Net credited to wallet should be ₦925 (92500 kobo)
    $payload = [
        'id' => 'evt_test_incoming_direct_funding',
        'type' => 'payment.received',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'merchant' => [
                'id' => 'mer_test_id',
                'business_name' => 'Korak Digitals',
            ],
            'customer' => [
                'name' => 'Deborah Utoo',
                'email' => $customer->email,
            ],
            'account' => [
                'account_number' => '3138435373',
                'reference' => 'MPVAT-P_TEST_REF',
                'bank_name' => 'Paga',
                'account_name' => 'Korak/DeborahUtoo',
            ],
            'transaction' => [
                'reference' => 'tx_test_direct_transfer_1000',
                'amount' => 1000.00, // ₦1,000 gross
                'currency' => 'NGN',
                'status' => 'success',
            ],
        ],
    ];

    $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $payloadJson, $secret);

    $response = $this->withHeaders([
        'X-CoFinance-Signature' => $signature,
        'Content-Type' => 'application/json',
    ])->postJson('/api/v1/webhooks/cofinance', $payload);

    $response->assertOk();

    // Verify wallet was credited with NET amount: ₦925 = 92,500 kobo
    $wallet->refresh();
    expect($wallet->balance)->toBe(92500);

    // Verify FundingRecord stored fee breakdown
    $fundingRecord = FundingRecord::where('provider_reference', 'tx_test_direct_transfer_1000')->first();
    expect($fundingRecord)->not->toBeNull();
    expect($fundingRecord->gross_amount)->toBe(100000); // ₦1,000
    expect($fundingRecord->fee)->toBe(7500); // ₦75 fee
    expect($fundingRecord->amount)->toBe(92500); // ₦925 net credited
    expect($fundingRecord->status)->toBe(FundingStatus::SUCCESSFUL);

    // Verify ledger entry has fee metadata
    $ledger = $wallet->ledgerEntries()->latest('id')->first();
    expect($ledger)->not->toBeNull();
    expect($ledger->amount)->toBe(92500);
    expect($ledger->metadata['gross_amount'])->toBe(100000);
    expect($ledger->metadata['fee'])->toBe(7500);
    expect($ledger->metadata['net_amount'])->toBe(92500);

    // Verify notification sent with net amount
    Notification::assertSentTo($customer, WalletFundedNotification::class, function (WalletFundedNotification $notification): bool {
        return $notification->amountInKobo === 92500;
    });
});
