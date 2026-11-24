<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\Wallet;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated guest trying to access customers page to admin login', function (): void {
    $response = $this->get(route('admin.customers.index'));

    $response->assertRedirect(route('admin.login'));
});

it('allows authenticated admin to view customers page with 4 stat cards, table and actions', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create([
        'username' => 'testuser',
        'email' => 'testuser@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'is_locked' => false,
    ]);
    $wallet = Wallet::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 500000, // ₦5,000.00
    ]);
    CustomerVirtualAccount::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'bank_name' => 'Wema Bank',
        'account_number' => '1234567890',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.index'));

    $response->assertOk()
        ->assertViewIs('admin.customers.index')
        ->assertSee('Customers')
        ->assertSee('Total Customers')
        ->assertSee('Verified Emails')
        ->assertSee('Virtual Accounts')
        ->assertSee('Locked Accounts')
        ->assertSee('testuser')
        ->assertSee('testuser@example.com')
        ->assertSee('Wema Bank')
        ->assertSee('1234567890')
        ->assertSee('5,000.00')
        ->assertSee('View')
        ->assertSee('Lock')
        ->assertSee('customers-datatable');
});

it('filters customers by search keyword', function (): void {
    $admin = Admin::factory()->create();
    Customer::factory()->create([
        'username' => 'alpha_user',
        'email' => 'alpha@example.com',
    ]);
    Customer::factory()->create([
        'username' => 'beta_user',
        'email' => 'beta@example.com',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.index', ['search' => 'alpha']));

    $response->assertOk()
        ->assertSee('alpha_user')
        ->assertDontSee('beta_user');
});

it('filters customers by email verification status', function (): void {
    $admin = Admin::factory()->create();
    Customer::factory()->create([
        'username' => 'verified_user',
        'email_verified_at' => now(),
    ]);
    Customer::factory()->create([
        'username' => 'pending_account',
        'email_verified_at' => null,
    ]);

    $responseVerified = $this->actingAs($admin, 'admin')->get(route('admin.customers.index', ['status' => 'verified']));
    $responseVerified->assertOk()
        ->assertSee('verified_user')
        ->assertDontSee('pending_account');

    $responseUnverified = $this->actingAs($admin, 'admin')->get(route('admin.customers.index', ['status' => 'unverified']));
    $responseUnverified->assertOk()
        ->assertSee('pending_account')
        ->assertDontSee('verified_user');
});

it('allows admin to view single customer details profile page', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create([
        'username' => 'profile_customer',
        'email' => 'profile@example.com',
    ]);
    $wallet = Wallet::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 250000,
    ]);
    CustomerVirtualAccount::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'bank_name' => 'Providus Bank',
        'account_number' => '9988776655',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.show', $customer));

    $response->assertOk()
        ->assertViewIs('admin.customers.show')
        ->assertSee('profile_customer')
        ->assertSee('profile@example.com')
        ->assertSee('Providus Bank')
        ->assertSee('9988776655')
        ->assertSee('2,500.00');
});

it('toggles customer lock status via ajax endpoint and blocks login', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create([
        'username' => 'lockable_user',
        'is_locked' => false,
    ]);

    expect($customer->is_locked)->toBeFalse();

    // Lock user via POST
    $responseLock = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.toggle-lock', $customer));

    $responseLock->assertOk()
        ->assertJson([
            'status' => 'success',
            'is_locked' => true,
        ]);

    $customer->refresh();
    expect($customer->is_locked)->toBeTrue()
        ->and($customer->locked_at)->not->toBeNull();

    // Verify LoginLockoutService recognises lock
    $lockoutService = app(LoginLockoutService::class);
    expect($lockoutService->isLocked($customer))->toBeTrue();

    // Unlock user via POST
    $responseUnlock = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.customers.toggle-lock', $customer));

    $responseUnlock->assertOk()
        ->assertJson([
            'status' => 'success',
            'is_locked' => false,
        ]);

    $customer->refresh();
    expect($customer->is_locked)->toBeFalse()
        ->and($customer->locked_at)->toBeNull();
    expect($lockoutService->isLocked($customer))->toBeFalse();
});

it('allows admin to delete customer and cascades associated data', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create([
        'username' => 'delete_me_user',
    ]);
    $wallet = Wallet::factory()->create([
        'customer_id' => $customer->id,
    ]);
    $virtualAccount = CustomerVirtualAccount::factory()->create([
        'customer_id' => $customer->id,
        'wallet_id' => $wallet->id,
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->deleteJson(route('admin.customers.destroy', $customer));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Wallet::find($wallet->id))->toBeNull()
        ->and(CustomerVirtualAccount::find($virtualAccount->id))->toBeNull();
});
