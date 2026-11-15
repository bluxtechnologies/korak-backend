<?php

use App\Models\Customer;
use App\Notifications\DeviceVerifiedNotification;
use App\Notifications\LockoutNotification;
use App\Notifications\PasswordChangedNotification;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('login');
    RateLimiter::clear('password-reset');
    config(['auth.device_verification_enabled' => false]);
});

it('requires authentication for notification endpoints per Doc 06 & Doc 11', function (): void {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
    $this->getJson('/api/v1/notifications/unread-count')->assertStatus(401);
    $this->patchJson('/api/v1/notifications/'.Str::uuid())->assertStatus(401);
    $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
});

it('lists paginated notifications scoped strictly to the authenticated customer per Doc 11 §5', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    // Create 3 notifications for customer1
    $customer1->notify(new PasswordChangedNotification);
    $customer1->notify(new LockoutNotification(15));
    $customer1->notify(new DeviceVerifiedNotification('Chrome on MacOS', '1.2.3.4'));

    // Create 1 notification for customer2
    $customer2->notify(new PasswordChangedNotification);

    $response = $this->actingAs($customer1, 'customer')
        ->getJson('/api/v1/notifications');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'title',
                    'message',
                    'category',
                    'data',
                    'read_at',
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

    expect($response->json('meta.total'))->toBe(3)
        ->and(count($response->json('data')))->toBe(3);

    // Verify customer2 sees only their 1 notification
    $response2 = $this->actingAs($customer2, 'customer')
        ->getJson('/api/v1/notifications');

    $response2->assertOk();
    expect($response2->json('meta.total'))->toBe(1);
});

it('filters notifications by unread state per Doc 11 §5', function (): void {
    $customer = Customer::factory()->create();

    $customer->notify(new PasswordChangedNotification);
    $customer->notify(new LockoutNotification(15));

    /** @var DatabaseNotification $firstNotification */
    $firstNotification = $customer->notifications()->first();
    $firstNotification->markAsRead();

    // Query unread=1
    $unreadResponse = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications?unread=1');

    $unreadResponse->assertOk();
    expect($unreadResponse->json('meta.total'))->toBe(1)
        ->and($unreadResponse->json('data.0.read_at'))->toBeNull();

    // Query unread=0
    $readResponse = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications?unread=0');

    $readResponse->assertOk();
    expect($readResponse->json('meta.total'))->toBe(1)
        ->and($readResponse->json('data.0.read_at'))->not->toBeNull();

    // Query filter[read]=false
    $filterUnread = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications?filter[read]=false');

    $filterUnread->assertOk();
    expect($filterUnread->json('meta.total'))->toBe(1);

    // Query filter[read]=true
    $filterRead = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications?filter[read]=true');

    $filterRead->assertOk();
    expect($filterRead->json('meta.total'))->toBe(1);
});

it('returns unread notifications count per Doc 11 §5', function (): void {
    $customer = Customer::factory()->create();

    $customer->notify(new PasswordChangedNotification);
    $customer->notify(new LockoutNotification(15));

    $response = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications/unread-count');

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'unread_count' => 2,
            ],
            'meta' => [],
        ]);

    // Mark 1 as read
    /** @var DatabaseNotification $notification */
    $notification = $customer->unreadNotifications()->first();
    $notification->markAsRead();

    $responseAfter = $this->actingAs($customer, 'customer')
        ->getJson('/api/v1/notifications/unread-count');

    $responseAfter->assertOk()
        ->assertExactJson([
            'data' => [
                'unread_count' => 1,
            ],
            'meta' => [],
        ]);
});

it('marks a single notification as read per Doc 11 §5', function (): void {
    $customer = Customer::factory()->create();
    $customer->notify(new PasswordChangedNotification);

    /** @var DatabaseNotification $notification */
    $notification = $customer->unreadNotifications()->first();
    expect($notification->read_at)->toBeNull();

    $response = $this->actingAs($customer, 'customer')
        ->patchJson("/api/v1/notifications/{$notification->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'title',
                'message',
                'category',
                'data',
                'read_at',
                'created_at',
            ],
            'meta',
        ]);

    expect($response->json('data.read_at'))->not->toBeNull()
        ->and($notification->fresh()->read_at)->not->toBeNull();
});

it('returns 404 when marking a notification belonging to another customer per Doc 11 §5', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $customer1->notify(new PasswordChangedNotification);

    /** @var DatabaseNotification $notification1 */
    $notification1 = $customer1->unreadNotifications()->first();

    // Customer 2 tries to mark Customer 1's notification as read
    $response = $this->actingAs($customer2, 'customer')
        ->patchJson("/api/v1/notifications/{$notification1->id}");

    $response->assertStatus(404)
        ->assertJson([
            'code' => 'notification_not_found',
        ]);

    // Notification remains unread
    expect($notification1->fresh()->read_at)->toBeNull();
});

it('returns 404 when marking non-existent notification', function (): void {
    $customer = Customer::factory()->create();

    $response = $this->actingAs($customer, 'customer')
        ->patchJson('/api/v1/notifications/'.Str::uuid());

    $response->assertStatus(404)
        ->assertJson([
            'code' => 'notification_not_found',
        ]);
});

it('marks all unread notifications as read per Doc 11 §5', function (): void {
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();

    $customer1->notify(new PasswordChangedNotification);
    $customer1->notify(new LockoutNotification(15));
    $customer2->notify(new PasswordChangedNotification);

    expect($customer1->unreadNotifications()->count())->toBe(2)
        ->and($customer2->unreadNotifications()->count())->toBe(1);

    $response = $this->actingAs($customer1, 'customer')
        ->postJson('/api/v1/notifications/read-all');

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'message' => 'All notifications marked as read.',
            ],
            'meta' => [],
        ]);

    // Customer 1 has 0 unread notifications
    expect($customer1->unreadNotifications()->count())->toBe(0);

    // Customer 2 unread notifications remain untouched
    expect($customer2->unreadNotifications()->count())->toBe(1);
});

it('confirms security notification classes implement ShouldQueue and use mail & database channels per Doc 11', function (): void {
    $notifications = [
        new LockoutNotification(15),
        new PasswordChangedNotification,
        new DeviceVerifiedNotification('MacBook Pro', '127.0.0.1'),
    ];

    foreach ($notifications as $notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);
        $channels = $notification->via(null);
        expect($channels)->toContain('mail')
            ->and($channels)->toContain('database');
    }
});

it('writes PasswordChangedNotification to database when password reset completes', function (): void {
    $customer = Customer::factory()->create([
        'email' => 'reset_notify@example.com',
        'password' => 'OldPassword123!',
    ]);

    $token = Password::broker('customers')->createToken($customer);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'reset_notify@example.com',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertOk();

    // Verify database notification was stored
    expect($customer->notifications()->count())->toBe(1);

    /** @var DatabaseNotification $notification */
    $notification = $customer->notifications()->first();
    expect($notification->type)->toBe(PasswordChangedNotification::class)
        ->and($notification->data['category'])->toBe('security')
        ->and($notification->data['title'])->toBe('Password Changed');
});

it('writes LockoutNotification to database when account is locked out', function (): void {
    $customer = Customer::factory()->create([
        'username' => 'lockout_test_user',
        'email' => 'lockout_test@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $service = app(LoginLockoutService::class);
    $service->lock($customer);

    // Verify database notification was created
    expect($customer->notifications()->count())->toBe(1);

    /** @var DatabaseNotification $notification */
    $notification = $customer->notifications()->first();
    expect($notification->type)->toBe(LockoutNotification::class)
        ->and($notification->data['category'])->toBe('security')
        ->and($notification->data['title'])->toBe('Account Temporarily Locked');
});
