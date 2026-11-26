<?php

use App\Enums\AuditEventName;
use App\Enums\FundingStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Admin;
use App\Models\AuditEvent;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('allows admin to view customer analytics and statistics with financial metrics and chart payloads', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'analytics_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 1000000]);

    FundingRecord::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'gross_amount' => 500000,
        'fee' => 7500,
        'amount' => 492500,
        'status' => FundingStatus::SUCCESSFUL,
    ]);

    BillOrder::create([
        'ulid' => (string) str()->ulid(),
        'customer_id' => $customer->id,
        'reference' => 'TEST-ORD-DATA-01',
        'category' => 'data',
        'service_code' => 'mtn_data',
        'service_name' => 'MTN 1GB Data',
        'customer_identifier' => '08012345678',
        'type' => 'data',
        'amount' => 30000,
        'face_amount' => 30000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    BillOrder::create([
        'ulid' => (string) str()->ulid(),
        'customer_id' => $customer->id,
        'reference' => 'TEST-ORD-AIR-01',
        'category' => 'airtime',
        'service_code' => 'airtel_airtime',
        'service_name' => 'Airtel Airtime',
        'customer_identifier' => '08098765432',
        'type' => 'airtime',
        'amount' => 10000,
        'face_amount' => 10000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    BillOrder::create([
        'ulid' => (string) str()->ulid(),
        'customer_id' => $customer->id,
        'reference' => 'TEST-ORD-ELEC-01',
        'category' => 'electricity',
        'service_code' => 'ekedc_prepaid',
        'service_name' => 'EKEDC Electric Prepaid',
        'customer_identifier' => '1122334455',
        'type' => 'electricity',
        'amount' => 50000,
        'face_amount' => 50000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'failed',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.analytics', $customer));

    $response->assertOk()
        ->assertViewIs('admin.customers.analytics')
        ->assertSee('Analytics & Stats: @analytics_user')
        ->assertSee('Lifetime Deposits')
        ->assertSee('Total Spend')
        ->assertSee('Order Success Rate')
        ->assertSee('Monthly Cashflow Trend')
        ->assertSee('Spend by Category')
        ->assertSee('Top Purchased Products')
        ->assertSee('MTN 1GB Data');
});

it('allows admin to view customer bank inflows with gross, fee, and net breakdown', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'inflow_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id]);

    FundingRecord::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'reference' => 'KORAK-FUND-TEST-001',
        'provider_reference' => 'ext_bank_9988',
        'gross_amount' => 1000000, // ₦10,000.00
        'fee' => 7500,             // ₦75.00
        'amount' => 992500,        // ₦9,925.00
        'sender_name' => 'Alhaji Musa Dangote',
        'status' => FundingStatus::SUCCESSFUL,
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.inflows', $customer));

    $response->assertOk()
        ->assertViewIs('admin.customers.inflows')
        ->assertSee('Bank Inflows: @inflow_user')
        ->assertSee('Gross Bank Deposits')
        ->assertSee('Fees Deducted')
        ->assertSee('Net Wallet Credited')
        ->assertSee('KORAK-FUND-TEST-001')
        ->assertSee('Alhaji Musa Dangote')
        ->assertSee('10,000.00')
        ->assertSee('75.00')
        ->assertSee('9,925.00')
        ->assertSee('Settled');
});

it('allows admin to reconcile pending bank inflow directly via provider', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'reconcile_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 0]);

    $inflow = FundingRecord::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'reference' => 'KORAK-PENDING-REF-01',
        'provider_reference' => 'ext_pending_01',
        'gross_amount' => 200000,
        'fee' => 7500,
        'amount' => 192500,
        'status' => FundingStatus::PENDING,
    ]);

    // Mock FinityPay verifyPayment response
    Http::fake([
        '*/payments/verify/*' => Http::response([
            'status' => 'success',
            'data' => [
                'reference' => 'KORAK-PENDING-REF-01',
                'status' => 'success',
                'amount' => 2000,
            ],
        ], 200),
    ]);

    config(['services.finitypay.secret_key' => 'sk_live_test_key_12345']);

    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.customers.inflows.reconcile', [$customer, $inflow]));

    $response->assertRedirect();

    $inflow->refresh();
    expect($inflow->status)->toBe(FundingStatus::SUCCESSFUL);

    $wallet->refresh();
    expect($wallet->balance)->toBe(192500);

    $this->assertDatabaseHas('ledger_entries', [
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::CREDIT->value,
        'type' => LedgerEntryType::FUNDING->value,
        'reference' => 'KORAK-PENDING-REF-01',
        'amount' => 192500,
    ]);
});

it('allows admin to view audit logs and filter by category and ip', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'audit_target']);

    AuditEvent::create([
        'event' => AuditEventName::AUTH_LOGIN_SUCCESS,
        'actor_type' => Customer::class,
        'actor_id' => $customer->id,
        'ip_address' => '105.112.56.78',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'occurred_at' => now(),
    ]);

    AuditEvent::create([
        'event' => AuditEventName::AUTH_LOGIN_FAILED,
        'actor_type' => Customer::class,
        'actor_id' => $customer->id,
        'ip_address' => '197.210.45.10',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'occurred_at' => now()->subHour(),
    ]);

    // View all logs
    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.audit-logs', $customer));
    $response->assertOk()
        ->assertViewIs('admin.customers.audit-logs')
        ->assertSee('auth.login_success')
        ->assertSee('auth.login_failed')
        ->assertSee('105.112.56.78')
        ->assertSee('197.210.45.10');

    // Filter by IP
    $filterResponse = $this->actingAs($admin, 'admin')->get(route('admin.customers.audit-logs', [
        $customer,
        'ip' => '105.112.56.78',
    ]));
    $filterResponse->assertOk()
        ->assertSee('105.112.56.78')
        ->assertDontSee('197.210.45.10');
});

it('allows admin to update customer profile and logs audit event', function (): void {
    $admin = Admin::factory()->create(['username' => 'auditing_admin']);
    $customer = Customer::factory()->create([
        'username' => 'profile_edit_user',
        'first_name' => 'OldFirst',
        'last_name' => 'OldLast',
        'phone' => '08011111111',
        'phone_number' => '08011111111',
        'email' => 'old_email@example.com',
    ]);

    $response = $this->actingAs($admin, 'admin')->put(route('admin.customers.update-profile', $customer), [
        'first_name' => 'NewFirst',
        'last_name' => 'NewLast',
        'phone_number' => '08099998888',
        'email' => 'new_email@example.com',
    ]);

    $response->assertRedirect();

    $customer->refresh();
    expect($customer->first_name)->toBe('NewFirst');
    expect($customer->last_name)->toBe('NewLast');
    expect($customer->phone_number)->toBe('08099998888');
    expect($customer->email)->toBe('new_email@example.com');

    $this->assertDatabaseHas('audit_events', [
        'event' => AuditEventName::PROFILE_UPDATED->value,
        'subject_id' => $customer->id,
    ]);
});

it('allows admin to toggle device verification and clear login lockouts', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['device_verification_enabled' => false]);

    // Toggle 2FA on
    $toggleResponse = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.toggle-device-verification', $customer));

    $toggleResponse->assertOk()
        ->assertJson([
            'status' => 'success',
            'device_verification_enabled' => true,
        ]);

    $customer->refresh();
    expect($customer->device_verification_enabled)->toBeTrue();

    // Toggle 2FA off
    $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.toggle-device-verification', $customer));

    $customer->refresh();
    expect($customer->device_verification_enabled)->toBeFalse();

    // Clear lockout
    $lockoutService = app(LoginLockoutService::class);
    $lockoutService->recordFailedAttempt($customer);

    $clearResponse = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.clear-lockout', $customer));

    $clearResponse->assertOk()
        ->assertJson(['status' => 'success']);

    expect($lockoutService->failedAttempts($customer))->toBe(0);
});

it('allows admin to generate virtual account for customer', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id]);

    // In mock mode, FinityPayService creates a virtual account immediately
    config(['services.finitypay.mock' => true]);

    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.customers.generate-virtual-account', $customer));

    $response->assertRedirect();

    $customer->refresh();
    expect($customer->virtualAccount)->not->toBeNull();
    expect($customer->virtualAccount->account_number)->not->toBeEmpty();
});

it('allows admin to export transactions, orders, and inflows to CSV', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'csv_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id]);

    LedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'reference' => 'CSV-LEDGER-01',
        'amount' => 50000,
        'balance_after' => 50000,
        'description' => 'Export test ledger',
    ]);

    BillOrder::create([
        'ulid' => (string) str()->ulid(),
        'customer_id' => $customer->id,
        'reference' => 'CSV-ORDER-01',
        'category' => 'airtime',
        'service_code' => 'airtime_csv',
        'service_name' => 'CSV Airtime',
        'customer_identifier' => '08012345678',
        'type' => 'airtime',
        'amount' => 20000,
        'face_amount' => 20000,
        'discount_amount' => 0,
        'currency' => 'NGN',
        'status' => 'completed',
    ]);

    FundingRecord::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'reference' => 'CSV-INFLOW-01',
        'gross_amount' => 100000,
        'fee' => 2000,
        'amount' => 98000,
        'status' => FundingStatus::SUCCESSFUL,
    ]);

    // Test transactions export
    $txRes = $this->actingAs($admin, 'admin')->get(route('admin.customers.transactions.export', $customer));
    $txRes->assertOk();
    expect($txRes->headers->get('Content-Type'))->toContain('text/csv');

    // Test orders export
    $orderRes = $this->actingAs($admin, 'admin')->get(route('admin.customers.orders.export', $customer));
    $orderRes->assertOk();
    expect($orderRes->headers->get('Content-Type'))->toContain('text/csv');

    // Test inflows export
    $inflowRes = $this->actingAs($admin, 'admin')->get(route('admin.customers.inflows.export', $customer));
    $inflowRes->assertOk();
    expect($inflowRes->headers->get('Content-Type'))->toContain('text/csv');
});
