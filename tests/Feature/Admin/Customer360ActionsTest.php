<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\CustomerTrustedDevice;
use App\Models\LedgerEntry;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Wallet;
use App\Notifications\SupportTicketReplyNotification;
use App\Notifications\WalletAdjustmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('allows admin to view customer dedicated transactions page with stats and ledger entries', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'fin_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 350000]); // ₦3,500.00

    LedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::CREDIT,
        'type' => LedgerEntryType::FUNDING,
        'amount' => 500000,
        'balance_after' => 500000,
        'reference' => 'TEST-FUND-01',
        'description' => 'Test deposit',
    ]);

    LedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::DEBIT,
        'type' => LedgerEntryType::ORDER_DEBIT,
        'amount' => 150000,
        'balance_after' => 350000,
        'reference' => 'TEST-DEBIT-01',
        'description' => 'Test order purchase',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.transactions', $customer));

    $response->assertOk()
        ->assertViewIs('admin.customers.transactions')
        ->assertSee('TEST-FUND-01')
        ->assertSee('TEST-DEBIT-01')
        ->assertSee('5,000.00')
        ->assertSee('1,500.00')
        ->assertSee('Total Credited')
        ->assertSee('Total Debited')
        ->assertSee('Adjust Balance');
});

it('allows admin to view customer dedicated orders page', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'order_user']);

    BillOrder::create([
        'ulid' => (string) str()->ulid(),
        'customer_id' => $customer->id,
        'reference' => 'KORAK-ORD-9912',
        'provider_reference' => 'FINITY-9912',
        'category' => 'airtime',
        'service_code' => 'mtn_airtime',
        'service_name' => 'MTN Airtime VTU',
        'customer_identifier' => '08012345678',
        'customer_name' => 'Order User',
        'amount' => 100000, // ₦1,000.00
        'face_amount' => 100000,
        'discount_amount' => 2000,
        'currency' => 'NGN',
        'status' => 'completed',
        'token' => null,
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.orders', $customer));

    $response->assertOk()
        ->assertViewIs('admin.customers.orders')
        ->assertSee('KORAK-ORD-9912')
        ->assertSee('MTN Airtime VTU')
        ->assertSee('08012345678')
        ->assertSee('Completed')
        ->assertSee('1,000.00');
});

it('allows admin to view customer dedicated tickets list and thread conversation', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'ticket_user']);

    $ticket = SupportTicket::create([
        'ulid' => (string) str()->ulid(),
        'reference' => 'TCK-2026-0001',
        'customer_id' => $customer->id,
        'subject' => 'Issue with wallet funding',
        'category' => TicketCategory::BILLING,
        'status' => TicketStatus::OPEN,
        'has_unread_reply' => false,
    ]);

    SupportTicketMessage::create([
        'ulid' => (string) str()->ulid(),
        'ticket_id' => $ticket->id,
        'author_type' => 'customer',
        'author_id' => $customer->id,
        'author_name' => 'Ticket User',
        'body' => 'I made a transfer but my wallet was not credited.',
        'created_at' => now(),
    ]);

    // View tickets list
    $responseList = $this->actingAs($admin, 'admin')->get(route('admin.customers.tickets', $customer));
    $responseList->assertOk()
        ->assertViewIs('admin.customers.tickets.index')
        ->assertSee('TCK-2026-0001')
        ->assertSee('Issue with wallet funding')
        ->assertSee('Open Thread');

    // View specific thread
    $responseShow = $this->actingAs($admin, 'admin')->get(route('admin.customers.tickets.show', [$customer, $ticket]));
    $responseShow->assertOk()
        ->assertViewIs('admin.customers.tickets.show')
        ->assertSee('I made a transfer but my wallet was not credited.')
        ->assertSee('Send Official Reply');
});

it('allows admin to reply to a customer ticket, update status, and notifies customer', function (): void {
    Notification::fake();

    $admin = Admin::factory()->create(['username' => 'superadmin']);
    $customer = Customer::factory()->create(['email' => 'client@example.com']);

    $ticket = SupportTicket::create([
        'ulid' => (string) str()->ulid(),
        'reference' => 'TCK-2026-0002',
        'customer_id' => $customer->id,
        'subject' => 'Data bundle failed',
        'category' => TicketCategory::AIRTIME_DATA,
        'status' => TicketStatus::OPEN,
        'has_unread_reply' => false,
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.customers.tickets.reply', [$customer, $ticket]), [
        'message' => 'We have confirmed your transaction and credited your line.',
        'status' => 'resolved',
    ]);

    $response->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::RESOLVED);
    expect($ticket->has_unread_reply)->toBeTrue();

    $this->assertDatabaseHas('support_ticket_messages', [
        'ticket_id' => $ticket->id,
        'author_type' => 'admin',
        'author_id' => $admin->id,
        'body' => 'We have confirmed your transaction and credited your line.',
    ]);

    Notification::assertSentTo($customer, SupportTicketReplyNotification::class);
});

it('allows admin to manually credit customer wallet and logs ledger adjustment', function (): void {
    Notification::fake();

    $admin = Admin::factory()->create(['username' => 'lead_admin']);
    $customer = Customer::factory()->create(['username' => 'credited_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100000]); // ₦1,000.00

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.wallet.adjust', $customer), [
            'action' => 'credit',
            'amount' => '2500.50', // ₦2,500.50
            'reason' => 'Compensation for failed airtime dispatch',
            'notify' => 1,
        ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'balance_kobo' => 350050,
                'balance_formatted' => '₦3,500.50',
            ],
        ]);

    $wallet->refresh();
    expect($wallet->balance)->toBe(350050);

    $this->assertDatabaseHas('ledger_entries', [
        'wallet_id' => $wallet->id,
        'direction' => 'credit',
        'type' => 'manual_adjustment',
        'amount' => 250050,
        'balance_after' => 350050,
    ]);

    Notification::assertSentTo($customer, WalletAdjustmentNotification::class);
});

it('allows admin to manually debit customer wallet when balance is sufficient', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'debited_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 500000]); // ₦5,000.00

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.wallet.adjust', $customer), [
            'action' => 'debit',
            'amount' => '1200.00',
            'reason' => 'Reversal of erroneous credit',
            'notify' => 0,
        ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'balance_kobo' => 380000,
                'balance_formatted' => '₦3,800.00',
            ],
        ]);

    $wallet->refresh();
    expect($wallet->balance)->toBe(380000);
});

it('rejects manual debit when amount exceeds customer available balance', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['username' => 'broke_user']);
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100000]); // ₦1,000.00

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.wallet.adjust', $customer), [
            'action' => 'debit',
            'amount' => '5000.00', // exceeds ₦1,000
            'reason' => 'Excess debit test',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ]);

    expect($response->json('message'))->toContain('Insufficient customer balance');

    $wallet->refresh();
    expect($wallet->balance)->toBe(100000); // Unchanged
});

it('allows admin to manually verify customer email', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create(['email_verified_at' => null]);

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.verify-email', $customer));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    $customer->refresh();
    expect($customer->email_verified_at)->not->toBeNull();
});

it('allows admin to terminate customer active sessions and trusted devices', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create();

    CustomerTrustedDevice::create([
        'customer_id' => $customer->id,
        'device_name' => 'Chrome on Windows',
        'device_token_hash' => hash('sha256', 'test_device_token'),
        'ip_address' => '127.0.0.1',
        'last_used_at' => now(),
    ]);

    expect($customer->trustedDevices()->count())->toBe(1);

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.terminate-sessions', $customer));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    expect($customer->trustedDevices()->count())->toBe(0);
});
