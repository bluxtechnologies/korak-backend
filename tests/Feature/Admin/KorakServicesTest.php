<?php

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\WebsiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    WebsiteService::flushCache();
});

it('allows authenticated admin to view korak services management page', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.services.index'));

    $response->assertOk()
        ->assertViewIs('admin.services.index')
        ->assertSee('Korak Services')
        ->assertSee('Airtime VTU')
        ->assertSee('Mobile Data Bundles')
        ->assertSee('Total Services')
        ->assertSee('Offline / Maintenance');
});

it('allows admin to toggle service active and inactive state via AJAX', function (): void {
    $admin = Admin::factory()->create();
    $service = WebsiteService::findByCode('airtime');

    expect($service->is_active)->toBeTrue();

    // Toggle to deactivated
    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.services.toggle', $service));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'is_active' => false,
        ]);

    $service->refresh();
    expect($service->is_active)->toBeFalse();
    expect(WebsiteService::isServiceActive('airtime'))->toBeFalse();

    // Toggle back to active
    $responseBack = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.services.toggle', $service));

    $responseBack->assertOk()
        ->assertJson([
            'status' => 'success',
            'is_active' => true,
        ]);

    $service->refresh();
    expect($service->is_active)->toBeTrue();
    expect(WebsiteService::isServiceActive('airtime'))->toBeTrue();
});

it('allows admin to update service details and maintenance notice', function (): void {
    $admin = Admin::factory()->create();
    $service = WebsiteService::findByCode('airtime');

    $response = $this->actingAs($admin, 'admin')
        ->putJson(route('admin.services.update', $service), [
            'name' => 'Airtime VTU (Updated)',
            'category' => 'telecom',
            'icon' => 'ti ti-phone',
            'description' => 'Updated description text',
            'unavailable_headline' => 'Airtime Down For Maintenance',
            'maintenance_message' => 'Upgrading network gateway. Back in 30 minutes.',
            'is_active' => 0,
            'sort_order' => 5,
        ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    $service->refresh();
    expect($service->name)->toBe('Airtime VTU (Updated)');
    expect($service->unavailable_headline)->toBe('Airtime Down For Maintenance');
    expect($service->maintenance_message)->toBe('Upgrading network gateway. Back in 30 minutes.');
    expect($service->is_active)->toBeFalse();
});

it('allows admin to register a new website service', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')
        ->postJson(route('admin.services.store'), [
            'name' => 'Recharge Card Printing',
            'code' => 'recharge_pin',
            'category' => 'telecom',
            'icon' => 'ti ti-printer',
            'description' => 'Generate and print recharge scratch cards.',
            'unavailable_headline' => 'PIN Generation Offline',
            'maintenance_message' => 'PIN generation is temporarily unavailable.',
            'is_active' => 1,
            'sort_order' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
        ]);

    $this->assertDatabaseHas('website_services', [
        'code' => 'recharge_pin',
        'name' => 'Recharge Card Printing',
        'is_active' => 1,
    ]);
});

it('renders clean service unavailable page showing nothing else when deactivated', function (): void {
    $service = WebsiteService::findByCode('airtime');
    $service->update([
        'is_active' => false,
        'unavailable_headline' => 'Airtime Is Currently Unavailable',
        'maintenance_message' => 'Routine maintenance in progress.',
    ]);

    $response = $this->get(route('services.show', 'airtime'));

    $response->assertOk()
        ->assertViewIs('services.unavailable')
        ->assertSee('Airtime Is Currently Unavailable')
        ->assertSee('Routine maintenance in progress.')
        ->assertSee('Service Temporarily Offline');
});

it('returns 503 service unavailable in public API when service is deactivated', function (): void {
    $service = WebsiteService::findByCode('airtime');
    $service->update([
        'is_active' => false,
        'maintenance_message' => 'Airtime API temporarily offline.',
    ]);

    $response = $this->getJson(route('api.v1.services.show', 'airtime'));

    $response->assertStatus(503)
        ->assertJson([
            'code' => 'service_unavailable',
            'message' => 'Airtime API temporarily offline.',
        ]);
});

it('rejects bill payment with 503 when service category is deactivated and does not debit wallet', function (): void {
    $service = WebsiteService::findByCode('airtime');
    $service->update([
        'is_active' => false,
        'maintenance_message' => 'Airtime VTU is undergoing maintenance.',
    ]);

    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 100000, // ₦1,000.00
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->postJson(route('api.v1.bills.pay'), [
            'service_code' => 'mtn_airtime',
            'service_name' => 'MTN Airtime VTU',
            'category' => 'airtime',
            'customer_identifier' => '08012345678',
            'amount' => 50000, // ₦500.00
            'face_amount' => 50000,
        ]);

    $response->assertStatus(503)
        ->assertJson([
            'code' => 'service_unavailable',
            'message' => 'Airtime VTU is undergoing maintenance.',
        ]);

    $wallet->refresh();
    expect($wallet->balance)->toBe(100000); // Intact, no debit occurred
});

it('rejects data bundles query when data service is deactivated', function (): void {
    $service = WebsiteService::findByCode('data');
    $service->update([
        'is_active' => false,
        'maintenance_message' => 'Data services currently unavailable.',
    ]);

    $response = $this->getJson(route('api.v1.bills.data-bundles', ['service_code' => 'MTN_DATA']));

    $response->assertStatus(503)
        ->assertJson([
            'code' => 'service_unavailable',
            'message' => 'Data services currently unavailable.',
        ]);
});

it('allows admin to preview the unavailable page', function (): void {
    $admin = Admin::factory()->create();
    $service = WebsiteService::findByCode('airtime');

    $response = $this->actingAs($admin, 'admin')->get(route('admin.services.preview', $service));

    $response->assertOk()
        ->assertViewIs('services.unavailable')
        ->assertSee('Admin Preview:');
});
