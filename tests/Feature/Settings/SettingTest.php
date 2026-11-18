<?php

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::flushCache();
});

it('creates the settings table with key and json value columns', function (): void {
    expect(Schema::hasTable('settings'))->toBeTrue();

    foreach (['id', 'key', 'value', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('settings', $column))->toBeTrue(
            "Expected column [{$column}] to exist on [settings] table."
        );
    }
});

it('enforces a unique constraint on the setting key', function (): void {
    DB::table('settings')->insert([
        'key' => 'site_name',
        'value' => json_encode('Korak'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(function (): void {
        DB::table('settings')->insert([
            'key' => 'site_name',
            'value' => json_encode('Duplicate'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    })->toThrow(QueryException::class);
});

it('returns the default when a setting key is missing', function (): void {
    expect(setting('site_name', 'Korak'))->toBe('Korak');
});

it('stores and retrieves site settings through the setting helper', function (): void {
    $stored = setting([
        'site_name' => 'Korak',
        'support_email' => 'support@example.com',
        'maintenance_mode' => false,
        'featured_categories' => ['airtime', 'data'],
    ]);

    expect($stored)->toBeTrue()
        ->and(setting('site_name'))->toBe('Korak')
        ->and(setting('support_email'))->toBe('support@example.com')
        ->and(setting('maintenance_mode'))->toBeFalse()
        ->and(setting('featured_categories'))->toBe(['airtime', 'data']);

    $this->assertDatabaseHas('settings', [
        'key' => 'site_name',
    ]);
});

it('returns every setting as a key value map when called without a key', function (): void {
    setting([
        'site_name' => 'Korak',
        'currency' => 'NGN',
    ]);

    expect(setting())->toBe([
        'site_name' => 'Korak',
        'currency' => 'NGN',
    ]);
});

it('updates an existing setting and serves the new value from cache', function (): void {
    setting(['site_name' => 'Korak']);

    expect(setting('site_name'))->toBe('Korak');

    setting(['site_name' => 'Korak Pay']);

    expect(setting('site_name'))->toBe('Korak Pay')
        ->and(Cache::has(Setting::CACHE_KEY))->toBeTrue();
});

it('forgets a setting so later reads fall back to the default', function (): void {
    setting(['site_name' => 'Korak']);

    Setting::forgetValue('site_name');

    expect(setting('site_name', 'Untitled'))->toBe('Untitled')
        ->and(setting())->not->toHaveKey('site_name');

    $this->assertDatabaseMissing('settings', [
        'key' => 'site_name',
    ]);
});

it('creates settings using SettingFactory', function (): void {
    $setting = Setting::factory()->create([
        'key' => 'site_tagline',
        'value' => 'Pay bills faster',
    ]);

    expect($setting)->toBeInstanceOf(Setting::class)
        ->and($setting->key)->toBe('site_tagline')
        ->and($setting->value)->toBe('Pay bills faster');
});
