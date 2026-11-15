<?php

use App\Models\Customer;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('password-reset');
    RateLimiter::clear('login');
    config(['auth.device_verification_enabled' => false]);
});

it('sends password reset link to customer with valid email per Doc 08 and Doc 22', function (): void {
    Notification::fake();

    $customer = Customer::factory()->create([
        'username' => 'alice_customer',
        'email' => 'alice@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'alice@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'If that email address is in our database, we have sent a password reset link.',
            ],
            'meta' => [],
        ]);

    Notification::assertSentTo(
        $customer,
        ResetPasswordNotification::class,
        function (ResetPasswordNotification $notification) use ($customer): bool {
            $mail = $notification->toMail($customer);

            return $mail->subject === 'Reset Your Password — Korak Digitals'
                && ! empty($notification->token)
                && str_contains($mail->actionUrl, 'token='.$notification->token)
                && str_contains($mail->actionUrl, urlencode($customer->email));
        }
    );
});

it('returns identical response when email does not exist to prevent account enumeration per Doc 08', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'If that email address is in our database, we have sent a password reset link.',
            ],
            'meta' => [],
        ]);

    Notification::assertNothingSent();
});

it('validates forgot-password request payload', function (): void {
    $response = $this->postJson('/api/v1/auth/forgot-password', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'code',
            'errors' => ['email'],
        ])
        ->assertJson([
            'code' => 'validation_failed',
        ]);

    $invalidEmailResponse = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'not-a-valid-email',
    ]);

    $invalidEmailResponse->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'code',
            'errors' => ['email'],
        ]);
});

it('throttles forgot-password requests exceeding limit per Doc 08 §2', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'rate_test@example.com',
        ]);
        $response->assertOk();
    }

    $blocked = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'rate_test@example.com',
    ]);

    $blocked->assertStatus(429)
        ->assertJson([
            'code' => 'too_many_requests',
        ]);
});

it('resets password with valid token and customer can login with new password per Doc 08', function (): void {
    Event::fake([PasswordReset::class]);

    $customer = Customer::factory()->create([
        'username' => 'bob_customer',
        'email' => 'bob@example.com',
        'password' => 'OldPassword123!',
    ]);

    $token = Password::broker('customers')->createToken($customer);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'bob@example.com',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'message' => 'Your password has been reset.',
            ],
            'meta' => [],
        ]);

    Event::assertDispatched(PasswordReset::class, function (PasswordReset $event) use ($customer): bool {
        return $event->user->id === $customer->id;
    });

    $freshCustomer = $customer->fresh();
    expect(Hash::check('NewSecurePassword123!', $freshCustomer->password))->toBeTrue()
        ->and(Hash::check('OldPassword123!', $freshCustomer->password))->toBeFalse();

    // Verify customer can log in with new password
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'login' => 'bob@example.com',
        'password' => 'NewSecurePassword123!',
    ]);

    $loginResponse->assertOk();
    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('invalidates database sessions for customer upon password reset per Doc 08 §3', function (): void {
    $customerA = Customer::factory()->create(['email' => 'a@example.com']);
    $customerB = Customer::factory()->create(['email' => 'b@example.com']);

    // Seed session rows
    DB::table('sessions')->insert([
        [
            'id' => Str::random(40),
            'user_id' => $customerA->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Browser-A',
            'payload' => 'payload-a',
            'last_activity' => time(),
        ],
        [
            'id' => Str::random(40),
            'user_id' => $customerB->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Browser-B',
            'payload' => 'payload-b',
            'last_activity' => time(),
        ],
    ]);

    expect(DB::table('sessions')->where('user_id', $customerA->id)->count())->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $customerB->id)->count())->toBe(1);

    $token = Password::broker('customers')->createToken($customerA);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'a@example.com',
        'password' => 'NewPasswordForA123!',
        'password_confirmation' => 'NewPasswordForA123!',
    ]);

    $response->assertOk();

    // Customer A's sessions are completely cleared; Customer B's sessions are untouched
    expect(DB::table('sessions')->where('user_id', $customerA->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $customerB->id)->count())->toBe(1);
});

it('prevents reusing password reset token (single-use per Doc 08 §1)', function (): void {
    $customer = Customer::factory()->create(['email' => 'singleuse@example.com']);
    $token = Password::broker('customers')->createToken($customer);

    $firstAttempt = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'singleuse@example.com',
        'password' => 'ResetOncePassword123!',
        'password_confirmation' => 'ResetOncePassword123!',
    ]);

    $firstAttempt->assertOk();

    // Replay attack with same token must fail
    $secondAttempt = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'singleuse@example.com',
        'password' => 'AnotherPassword123!',
        'password_confirmation' => 'AnotherPassword123!',
    ]);

    $secondAttempt->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_reset_token',
            'message' => 'Invalid or expired password reset token.',
        ]);
});

it('rejects expired password reset token per Doc 08 §1', function (): void {
    $customer = Customer::factory()->create(['email' => 'expired@example.com']);
    $token = Password::broker('customers')->createToken($customer);

    // Fast forward past the 60-minute expiration window
    $this->travel(61)->minutes();

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'expired@example.com',
        'password' => 'ExpiredPassword123!',
        'password_confirmation' => 'ExpiredPassword123!',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_reset_token',
            'message' => 'Invalid or expired password reset token.',
        ]);
});

it('rejects invalid token with generic error to prevent account enumeration per Doc 08', function (): void {
    $customer = Customer::factory()->create(['email' => 'realuser@example.com']);

    // Attempt with existing user but fake token
    $responseRealUser = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-fake-token-123',
        'email' => 'realuser@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $responseRealUser->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_reset_token',
            'message' => 'Invalid or expired password reset token.',
        ]);

    // Attempt with non-existent user and fake token
    $responseUnknownUser = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-fake-token-123',
        'email' => 'unknown@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    // Both must return identical generic 400 error
    $responseUnknownUser->assertStatus(400)
        ->assertJson([
            'code' => 'invalid_reset_token',
            'message' => 'Invalid or expired password reset token.',
        ]);
});

it('validates reset-password request payload', function (): void {
    $response = $this->postJson('/api/v1/auth/reset-password', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'code',
            'errors' => ['token', 'email', 'password'],
        ]);

    // Password confirmation mismatch
    $mismatchResponse = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'some-token',
        'email' => 'test@example.com',
        'password' => 'ValidPassword123!',
        'password_confirmation' => 'DifferentPassword123!',
    ]);

    $mismatchResponse->assertStatus(422)
        ->assertJsonStructure([
            'errors' => ['password'],
        ]);

    // Password too short (< 8 chars)
    $shortResponse = $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'some-token',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $shortResponse->assertStatus(422)
        ->assertJsonStructure([
            'errors' => ['password'],
        ]);
});

it('confirms ResetPasswordNotification implements ShouldQueue per Doc 11', function (): void {
    $implements = class_implements(ResetPasswordNotification::class);
    expect($implements)->toHaveKey(ShouldQueue::class);
});
