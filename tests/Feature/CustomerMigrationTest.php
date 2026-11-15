<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates customers table with all required columns and types per Doc 07', function (): void {
    expect(Schema::hasTable('customers'))->toBeTrue();

    $expectedColumns = [
        'id',
        'ulid',
        'username',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('customers', $column))->toBeTrue(
            "Expected column [{$column}] to exist on [customers] table."
        );
    }
});

it('enforces unique constraint on ulid column', function (): void {
    $ulid = (string) Str::ulid();

    DB::table('customers')->insert([
        'ulid' => $ulid,
        'username' => 'customer_one',
        'email' => 'one@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function () use ($ulid): void {
        DB::table('customers')->insert([
            'ulid' => $ulid,
            'username' => 'customer_two',
            'email' => 'two@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('enforces unique constraint on username column', function (): void {
    DB::table('customers')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'unique_user',
        'email' => 'user1@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function (): void {
        DB::table('customers')->insert([
            'ulid' => (string) Str::ulid(),
            'username' => 'unique_user',
            'email' => 'user2@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('enforces unique constraint on email column', function (): void {
    DB::table('customers')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'customer_a',
        'email' => 'common@example.com',
        'password' => 'secret_hash',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function (): void {
        DB::table('customers')->insert([
            'ulid' => (string) Str::ulid(),
            'username' => 'customer_b',
            'email' => 'common@example.com',
            'password' => 'secret_hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('allows nullable email_verified_at and remember_token', function (): void {
    $inserted = DB::table('customers')->insert([
        'ulid' => (string) Str::ulid(),
        'username' => 'nullable_test',
        'email' => 'nullable@example.com',
        'email_verified_at' => null,
        'password' => 'secret_hash',
        'remember_token' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($inserted)->toBeTrue();

    $customer = DB::table('customers')->where('username', 'nullable_test')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->email_verified_at)->toBeNull()
        ->and($customer->remember_token)->toBeNull();
});

it('can reverse migration cleanly', function (): void {
    $migration = require database_path('migrations/2026_11_14_000001_create_customers_table.php');

    expect(Schema::hasTable('customers'))->toBeTrue();

    $migration->down();

    expect(Schema::hasTable('customers'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('customers'))->toBeTrue();
});
