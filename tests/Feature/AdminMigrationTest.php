<?php

use App\Enums\AdminRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates admins table with all required columns and types per Doc 04 and Doc 07', function (): void {
    expect(Schema::hasTable('admins'))->toBeTrue();

    $expectedColumns = [
        'id',
        'ulid',
        'username',
        'email',
        'email_verified_at',
        'password',
        'role',
        'is_active',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('admins', $column))->toBeTrue(
            "Expected column [{$column}] to exist on [admins] table."
        );
    }
});

it('applies default role and is_active values', function (): void {
    DB::table('admins')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'default_admin',
        'email' => 'admin@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = DB::table('admins')->where('username', 'default_admin')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(AdminRole::Admin->value)
        ->and((bool) $admin->is_active)->toBeTrue();
});

it('enforces unique constraint on ulid column in admins', function (): void {
    $ulid = (string) Str::ulid();

    DB::table('admins')->insert([
        'ulid' => $ulid,
        'username' => 'admin_one',
        'email' => 'admin1@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function () use ($ulid): void {
        DB::table('admins')->insert([
            'ulid' => $ulid,
            'username' => 'admin_two',
            'email' => 'admin2@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('enforces unique constraint on username and email in admins', function (): void {
    DB::table('admins')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'ops_lead',
        'email' => 'ops@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Duplicate username
    expect(function (): void {
        DB::table('admins')->insert([
            'ulid' => (string) Str::ulid(),
            'username' => 'ops_lead',
            'email' => 'different@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);

    // Duplicate email
    expect(function (): void {
        DB::table('admins')->insert([
            'ulid' => (string) Str::ulid(),
            'username' => 'different_lead',
            'email' => 'ops@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('maintains strict separation between customers and admins tables per Doc 04 and Doc 07', function (): void {
    expect(Schema::hasTable('customers'))->toBeTrue()
        ->and(Schema::hasTable('admins'))->toBeTrue();

    // The same username can exist independently across both separate identity tables
    DB::table('customers')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'collins',
        'email' => 'collins.customer@example.com',
        'password' => 'hash_customer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('admins')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'collins',
        'email' => 'collins.admin@example.com',
        'password' => 'hash_admin',
        'role' => AdminRole::SuperAdmin->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customer = DB::table('customers')->where('username', 'collins')->first();
    $admin = DB::table('admins')->where('username', 'collins')->first();

    expect($customer)->not->toBeNull()
        ->and($admin)->not->toBeNull()
        ->and($customer->email)->toBe('collins.customer@example.com')
        ->and($admin->email)->toBe('collins.admin@example.com')
        ->and($admin->role)->toBe(AdminRole::SuperAdmin->value);
});

it('can reverse admins migration cleanly', function (): void {
    $migration = require database_path('migrations/2026_11_14_000002_create_admins_table.php');

    expect(Schema::hasTable('admins'))->toBeTrue();

    $migration->down();

    expect(Schema::hasTable('admins'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('admins'))->toBeTrue();
});
