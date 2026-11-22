<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated guest trying to access customers page to admin login', function (): void {
    $response = $this->get(route('admin.customers.index'));

    $response->assertRedirect(route('admin.login'));
});

it('allows authenticated admin to view customers page with header and table', function (): void {
    $admin = Admin::factory()->create();
    $customer = Customer::factory()->create([
        'username' => 'testuser',
        'email' => 'testuser@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
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
        ->assertSee('testuser')
        ->assertSee('testuser@example.com')
        ->assertSee('Wema Bank')
        ->assertSee('1234567890')
        ->assertSee('5,000.00');
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
