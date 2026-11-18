<?php

use App\Enums\AuditEventName;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config(['auth.device_verification_enabled' => false]);
});

it('requires authentication for all profile and settings endpoints', function (): void {
    $this->getJson('/api/v1/profile')->assertStatus(401);
    $this->patchJson('/api/v1/profile')->assertStatus(401);
    $this->putJson('/api/v1/profile/password')->assertStatus(401);
    $this->getJson('/api/v1/profile/sessions')->assertStatus(401);
    $this->deleteJson('/api/v1/profile/sessions/other')->assertStatus(401);
    $this->deleteJson('/api/v1/profile/sessions/some-hash')->assertStatus(401);
    $this->getJson('/api/v1/profile/security-activity')->assertStatus(401);
});

it('retrieves the authenticated customer profile per Roadmap B11.1', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'johndoe',
        'email' => 'john@example.com',
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/profile');

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $customer->ulid,
                'username' => 'johndoe',
                'name' => 'johndoe',
                'first_name' => null,
                'last_name' => null,
                'full_name' => 'johndoe',
                'email' => 'john@example.com',
                'phone' => $customer->phone,
                'phone_number' => $customer->phone_number,
                'email_verified_at' => $customer->email_verified_at?->toIso8601String(),
                'has_virtual_account' => false,
                'virtual_account' => null,
                'created_at' => $customer->created_at->toIso8601String(),
            ],
            'meta' => [],
        ]);
});

it('updates customer profile and records audit event per Roadmap B11.1', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'initial_user',
        'email' => 'initial@example.com',
    ]);

    $response = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'username' => 'updated_user',
        ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'username' => 'updated_user',
                'email' => 'initial@example.com',
            ],
            'meta' => [],
        ]);

    expect($customer->fresh()->username)->toBe('updated_user');

    // Assert audit event recorded
    $event = AuditEvent::where('event', AuditEventName::PROFILE_UPDATED->value)->first();
    expect($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->metadata['updated_fields'])->toContain('username');
});

it('resets email_verified_at when customer email is updated', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'verified@example.com',
        'email_verified_at' => now(),
    ]);

    expect($customer->email_verified_at)->not->toBeNull();

    $response = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'email' => 'brand_new@example.com',
        ]);

    $response->assertOk();
    expect($customer->fresh()->email)->toBe('brand_new@example.com')
        ->and($customer->fresh()->email_verified_at)->toBeNull();
});

it('validates profile updates and prevents duplicate usernames or emails', function (): void {
    $otherCustomer = Customer::factory()->create([
        'username' => 'taken_name',
        'email' => 'taken@example.com',
    ]);

    $customer = Customer::factory()->create([
        'username' => 'my_name',
        'email' => 'my@example.com',
    ]);

    // Attempt to take other customer's username
    $nameConflict = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'username' => 'taken_name',
        ]);

    $nameConflict->assertStatus(422)
        ->assertJsonValidationErrors(['username']);

    // Attempt to take other customer's email
    $emailConflict = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'email' => 'taken@example.com',
        ]);

    $emailConflict->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    // Keeping own username and email should pass validation
    $selfUpdate = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/profile', [
            'username' => 'my_name',
            'email' => 'my@example.com',
        ]);

    $selfUpdate->assertOk();
});

it('changes password with current password verification and dispatches notification per Roadmap B11.2 & Doc 08 §1', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'email' => 'pass_change@example.com',
        'password' => 'OldPassword123!',
    ]);

    // 1. Fails when current password is wrong
    $wrongCurrent = $this->actingAs($customer, 'customer')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'IncorrectCurrent!',
            'password' => 'BrandNewSecure123!',
            'password_confirmation' => 'BrandNewSecure123!',
        ]);

    $wrongCurrent->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);

    // 2. Fails when new password is same as current password
    $samePassword = $this->actingAs($customer, 'customer')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'OldPassword123!',
            'password_confirmation' => 'OldPassword123!',
        ]);

    $samePassword->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    // 3. Fails when new password is under 12 characters (Doc 08 §1)
    $shortPassword = $this->actingAs($customer, 'customer')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'Short12!',
            'password_confirmation' => 'Short12!',
        ]);

    $shortPassword->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    // 4. Success path
    $success = $this->actingAs($customer, 'customer')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'BrandNewSecure123!',
            'password_confirmation' => 'BrandNewSecure123!',
        ]);

    $success->assertOk()
        ->assertExactJson([
            'data' => [
                'message' => 'Password changed successfully.',
            ],
            'meta' => [],
        ]);

    // Assert notification was sent
    Notification::assertSentTo($customer, PasswordChangedNotification::class);

    // Assert audit event was recorded
    $event = AuditEvent::where('event', AuditEventName::AUTH_PASSWORD_CHANGED->value)->first();
    expect($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($customer->id);

    // Assert customer can login with new password
    Auth::guard('customer')->logout();
    $login = $this->postJson('/api/v1/auth/login', [
        'login' => 'pass_change@example.com',
        'password' => 'BrandNewSecure123!',
    ]);

    $login->assertOk();
    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('lists active database sessions for the authenticated customer per Roadmap B11.3 & Doc 08 §3', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    // Seed session rows
    DB::table('sessions')->insert([
        [
            'id' => 'session_1_c1',
            'user_id' => $customer1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome',
            'payload' => 'payload1',
            'last_activity' => time() - 100,
        ],
        [
            'id' => 'session_2_c1',
            'user_id' => $customer1->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 Safari',
            'payload' => 'payload2',
            'last_activity' => time() - 50,
        ],
        [
            'id' => 'session_other_c2',
            'user_id' => $customer2->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 Firefox',
            'payload' => 'payload3',
            'last_activity' => time(),
        ],
    ]);

    $response = $this->actingAs($customer1, 'customer')
        ->getJson('/api/v1/profile/sessions');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'ip_address',
                    'user_agent',
                    'is_current',
                    'last_activity',
                ],
            ],
            'meta',
        ]);

    $items = $response->json('data');
    expect(count($items))->toBe(2);

    // Verify IDs are hashed with SHA-256 (not raw cookie IDs)
    expect($items[0]['id'])->toBe(hash('sha256', 'session_2_c1'))
        ->and($items[1]['id'])->toBe(hash('sha256', 'session_1_c1'));
});

it('signs out all other sessions requiring re-authentication per Roadmap B11.3', function (): void {
    $customer1 = Customer::factory()->create([
        'password' => 'MySecretPass123!',
    ]);
    $customer2 = Customer::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'current_c1_session',
            'user_id' => $customer1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Current Browser',
            'payload' => 'payload_current',
            'last_activity' => time(),
        ],
        [
            'id' => 'remote_c1_session',
            'user_id' => $customer1->id,
            'ip_address' => '192.168.1.5',
            'user_agent' => 'Remote Browser',
            'payload' => 'payload_remote',
            'last_activity' => time() - 200,
        ],
        [
            'id' => 'other_customer_session',
            'user_id' => $customer2->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Other Browser',
            'payload' => 'payload_c2',
            'last_activity' => time(),
        ],
    ]);

    // 1. Wrong current password fails with 422
    $wrongPass = $this->actingAs($customer1, 'customer')
        ->deleteJson('/api/v1/profile/sessions/other', [
            'current_password' => 'WrongPass!',
        ]);

    $wrongPass->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);

    // 2. Correct password revokes other sessions
    $response = $this->actingAs($customer1, 'customer')
        ->deleteJson('/api/v1/profile/sessions/other', [
            'current_password' => 'MySecretPass123!',
        ]);

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'message' => 'All other sessions have been signed out.',
            ],
            'meta' => [],
        ]);

    // Remote session for customer1 was deleted
    expect(DB::table('sessions')->where('id', 'remote_c1_session')->exists())->toBeFalse();

    // Customer2 session was NOT affected
    expect(DB::table('sessions')->where('id', 'other_customer_session')->exists())->toBeTrue();

    // Audit event recorded
    $event = AuditEvent::where('event', AuditEventName::SESSION_REVOKED_OTHER->value)->first();
    expect($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($customer1->id);
});

it('signs out a specific session by its opaque identifier per Roadmap B11.3', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'target_session_c1',
            'user_id' => $customer1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Target Session',
            'payload' => 'payload',
            'last_activity' => time(),
        ],
        [
            'id' => 'c2_session',
            'user_id' => $customer2->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'C2 Session',
            'payload' => 'payload2',
            'last_activity' => time(),
        ],
    ]);

    $targetHash = hash('sha256', 'target_session_c1');

    // Customer 2 cannot revoke Customer 1's session (404)
    $unauthorized = $this->actingAs($customer2, 'customer')
        ->deleteJson("/api/v1/profile/sessions/{$targetHash}");

    $unauthorized->assertStatus(404)
        ->assertJson(['code' => 'session_not_found']);

    // Customer 1 revokes own session
    $response = $this->actingAs($customer1, 'customer')
        ->deleteJson("/api/v1/profile/sessions/{$targetHash}");

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'message' => 'Session signed out successfully.',
            ],
            'meta' => [],
        ]);

    expect(DB::table('sessions')->where('id', 'target_session_c1')->exists())->toBeFalse();

    // Audit event recorded
    $event = AuditEvent::where('event', AuditEventName::SESSION_REVOKED->value)->first();
    expect($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($customer1->id);
});

it('lists customer recent security activity scoped strictly to the customer per Roadmap B11.4 & Doc 12 §5', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    // Create audit events for customer1
    AuditEvent::create([
        'occurred_at' => now()->subMinutes(10),
        'actor_type' => $customer1->getMorphClass(),
        'actor_id' => $customer1->id,
        'event' => AuditEventName::AUTH_LOGIN_SUCCESS,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome',
        'metadata' => ['device' => 'desktop'],
    ]);

    AuditEvent::create([
        'occurred_at' => now()->subMinutes(5),
        'actor_type' => $customer1->getMorphClass(),
        'actor_id' => $customer1->id,
        'event' => AuditEventName::AUTH_PASSWORD_CHANGED,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome',
        'metadata' => [],
    ]);

    // Create audit event for customer2
    AuditEvent::create([
        'occurred_at' => now()->subMinute(),
        'actor_type' => $customer2->getMorphClass(),
        'actor_id' => $customer2->id,
        'event' => AuditEventName::AUTH_LOGIN_SUCCESS,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Safari',
        'metadata' => [],
    ]);

    $response = $this->actingAs($customer1, 'customer')
        ->getJson('/api/v1/profile/security-activity');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'event',
                    'ip_address',
                    'user_agent',
                    'metadata',
                    'occurred_at',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);

    expect($response->json('meta.total'))->toBe(2);

    $events = $response->json('data');
    expect($events[0]['event'])->toBe('auth.password_changed')
        ->and($events[1]['event'])->toBe('auth.login_success');

    // Customer 2 sees only their 1 event
    $response2 = $this->actingAs($customer2, 'customer')
        ->getJson('/api/v1/profile/security-activity');

    $response2->assertOk();
    expect($response2->json('meta.total'))->toBe(1);
});
