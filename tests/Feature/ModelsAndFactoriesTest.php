<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Customer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('automatically generates a public ULID on Customer creation', function (): void {
    $customer = Customer::create([
        'username' => 'alice',
        'email' => 'alice@example.com',
        'password' => 'plaintext_secret',
    ]);

    expect($customer->ulid)->not->toBeEmpty()
        ->and(strlen($customer->ulid))->toBe(26)
        ->and(Str::isUlid($customer->ulid))->toBeTrue()
        ->and($customer->getRouteKeyName())->toBe('ulid');
});

it('preserves an explicitly provided ULID on Customer creation', function (): void {
    $explicitUlid = strtolower((string) Str::ulid());

    $customer = Customer::create([
        'ulid' => $explicitUlid,
        'username' => 'bob',
        'email' => 'bob@example.com',
        'password' => 'plaintext_secret',
    ]);

    expect($customer->ulid)->toBe($explicitUlid);
});

it('automatically hashes passwords and hides sensitive columns on Customer model', function (): void {
    $customer = Customer::create([
        'username' => 'charlie',
        'email' => 'charlie@example.com',
        'password' => 'my_secure_password',
        'email_verified_at' => now(),
    ]);

    expect($customer->email_verified_at)->toBeInstanceOf(Carbon::class)
        ->and(Hash::check('my_secure_password', $customer->password))->toBeTrue();

    $serialized = $customer->toArray();
    expect($serialized)->not->toHaveKey('password')
        ->and($serialized)->not->toHaveKey('remember_token');
});

it('automatically generates a public ULID on Admin creation', function (): void {
    $admin = Admin::create([
        'username' => 'sysadmin',
        'email' => 'sysadmin@example.com',
        'password' => 'admin_secret',
    ]);

    expect($admin->ulid)->not->toBeEmpty()
        ->and(strlen($admin->ulid))->toBe(26)
        ->and(Str::isUlid($admin->ulid))->toBeTrue()
        ->and($admin->getRouteKeyName())->toBe('ulid');
});

it('casts role to AdminRole enum and is_active to boolean on Admin model', function (): void {
    $admin = Admin::create([
        'username' => 'auditor',
        'email' => 'auditor@example.com',
        'password' => 'admin_secret',
        'role' => AdminRole::Support,
        'is_active' => true,
    ]);

    expect($admin->role)->toBe(AdminRole::Support)
        ->and($admin->role)->toBeInstanceOf(AdminRole::class)
        ->and($admin->is_active)->toBeTrue();

    $serialized = $admin->toArray();
    expect($serialized)->not->toHaveKey('password')
        ->and($serialized)->not->toHaveKey('remember_token');
});

it('creates customers using CustomerFactory and states', function (): void {
    $customer = Customer::factory()->create();
    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->email_verified_at)->not->toBeNull()
        ->and(Str::isUlid($customer->ulid))->toBeTrue();

    $unverified = Customer::factory()->unverified()->create();
    expect($unverified->email_verified_at)->toBeNull();
});

it('creates admins using AdminFactory and role states', function (): void {
    $defaultAdmin = Admin::factory()->create();
    expect($defaultAdmin->role)->toBe(AdminRole::Admin)
        ->and($defaultAdmin->is_active)->toBeTrue();

    $superAdmin = Admin::factory()->superAdmin()->create();
    expect($superAdmin->role)->toBe(AdminRole::SuperAdmin);

    $support = Admin::factory()->support()->create();
    expect($support->role)->toBe(AdminRole::Support);

    $inactive = Admin::factory()->inactive()->create();
    expect($inactive->is_active)->toBeFalse();

    $unverified = Admin::factory()->unverified()->create();
    expect($unverified->email_verified_at)->toBeNull();
});

it('runs DatabaseSeeder and seeds test customers and admins without real credentials', function (): void {
    $this->seed(DatabaseSeeder::class);

    $testCustomer = Customer::where('username', 'test_customer')->first();
    expect($testCustomer)->not->toBeNull()
        ->and($testCustomer->email)->toBe('customer@example.com')
        ->and(Hash::check('password', $testCustomer->password))->toBeTrue();

    $testSuperAdmin = Admin::where('username', 'superadmin')->orWhere('username', 'test_superadmin')->first();
    expect($testSuperAdmin)->not->toBeNull()
        ->and($testSuperAdmin->role)->toBe(AdminRole::SuperAdmin);

    $testAdmin = Admin::where('username', 'admin')->orWhere('username', 'test_admin')->first();
    expect($testAdmin)->not->toBeNull()
        ->and($testAdmin->role)->toBe(AdminRole::Admin);

    // Total counts verify factory batches were created
    expect(Customer::count())->toBeGreaterThanOrEqual(6)
        ->and(Admin::count())->toBeGreaterThanOrEqual(2);
});
