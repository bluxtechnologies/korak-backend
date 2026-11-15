<?php

use App\Enums\AdminRole;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::middleware(['api', 'auth:customer'])->get('/api/v1/customer-only', function () {
        return ApiResponse::success([
            'role' => 'customer',
            'username' => Auth::guard('customer')->user()?->username,
        ]);
    });

    Route::middleware(['api', 'auth:admin'])->get('/api/v1/admin/admin-only', function () {
        return ApiResponse::success([
            'role' => 'admin',
            'username' => Auth::guard('admin')->user()?->username,
        ]);
    });
});

it('authenticates customer independently without affecting admin guard', function (): void {
    $customer = Customer::factory()->create(['username' => 'customer_user']);

    Auth::guard('customer')->login($customer);

    expect(Auth::guard('customer')->check())->toBeTrue()
        ->and(Auth::guard('customer')->user()?->username)->toBe('customer_user')
        ->and(Auth::guard('admin')->check())->toBeFalse()
        ->and(Auth::guard('admin')->user())->toBeNull();
});

it('authenticates admin independently without affecting customer guard', function (): void {
    $admin = Admin::factory()->create([
        'username' => 'admin_user',
        'role' => AdminRole::SuperAdmin,
    ]);

    Auth::guard('admin')->login($admin);

    expect(Auth::guard('admin')->check())->toBeTrue()
        ->and(Auth::guard('admin')->user()?->username)->toBe('admin_user')
        ->and(Auth::guard('customer')->check())->toBeFalse()
        ->and(Auth::guard('customer')->user())->toBeNull();
});

it('protects customer endpoints against unauthenticated and admin sessions per Doc 04 and Doc 13', function (): void {
    // 1. Unauthenticated request -> 401
    $response = $this->getJson('/api/v1/customer-only');
    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);

    // 2. Admin session attempting to access customer endpoint -> 401
    $admin = Admin::factory()->create();
    $adminResponse = $this->actingAs($admin, 'admin')->getJson('/api/v1/customer-only');
    $adminResponse->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);

    // 3. Customer session accessing customer endpoint -> 200
    $customer = Customer::factory()->create(['username' => 'valid_customer']);
    $customerResponse = $this->actingAs($customer, 'customer')->getJson('/api/v1/customer-only');
    $customerResponse->assertOk()
        ->assertJson([
            'data' => [
                'role' => 'customer',
                'username' => 'valid_customer',
            ],
        ]);
});

it('protects admin endpoints against unauthenticated and customer sessions per Doc 04 and Doc 13', function (): void {
    // 1. Unauthenticated request -> 401
    $response = $this->getJson('/api/v1/admin/admin-only');
    $response->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);

    // 2. Customer session attempting to access admin endpoint -> 401
    $customer = Customer::factory()->create();
    $customerResponse = $this->actingAs($customer, 'customer')->getJson('/api/v1/admin/admin-only');
    $customerResponse->assertStatus(401)
        ->assertExactJson([
            'message' => 'Unauthenticated.',
            'code' => 'unauthenticated',
        ]);

    // 3. Admin session accessing admin endpoint -> 200
    $admin = Admin::factory()->create(['username' => 'valid_admin']);
    $adminResponse = $this->actingAs($admin, 'admin')->getJson('/api/v1/admin/admin-only');
    $adminResponse->assertOk()
        ->assertJson([
            'data' => [
                'role' => 'admin',
                'username' => 'valid_admin',
            ],
        ]);
});

it('configures distinct password reset brokers for customers and admins', function (): void {
    expect(config('auth.passwords.customers.provider'))->toBe('customers')
        ->and(config('auth.providers.customers.model'))->toBe(Customer::class)
        ->and(config('auth.passwords.admins.provider'))->toBe('admins')
        ->and(config('auth.providers.admins.model'))->toBe(Admin::class);

    $customer = Customer::factory()->create(['email' => 'reset_cust@example.com']);
    $retrievedCustomer = Password::broker('customers')->getUser(['email' => 'reset_cust@example.com']);
    expect($retrievedCustomer)->toBeInstanceOf(Customer::class)
        ->and($retrievedCustomer?->id)->toBe($customer->id);

    $admin = Admin::factory()->create(['email' => 'reset_admin@example.com']);
    $retrievedAdmin = Password::broker('admins')->getUser(['email' => 'reset_admin@example.com']);
    expect($retrievedAdmin)->toBeInstanceOf(Admin::class)
        ->and($retrievedAdmin?->id)->toBe($admin->id);
});

it('defaults to customer guard as the primary application guard', function (): void {
    expect(Auth::getDefaultDriver())->toBe('customer');
});
