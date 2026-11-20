<?php

use App\Models\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('admin_login|admin@example.com|127.0.0.1');
    RateLimiter::clear('admin_login|admin@example.com|::1');
    RateLimiter::clear('admin_forgot|127.0.0.1');
    RateLimiter::clear('admin_forgot|::1');
});

it('renders the administrator login page', function (): void {
    $response = $this->get(route('admin.login'));

    $response->assertOk()
        ->assertViewIs('admin.auth.login')
        ->assertSee('Login your account')
        ->assertSee('toastr.min.css')
        ->assertSee('toastr.min.js');
});

it('redirects authenticated admin away from login page to dashboard', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

    $response->assertRedirect(route('admin.dashboard'));
});

it('authenticates an active admin via AJAX with valid credentials', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
    ]);

    $response = $this->postJson(route('admin.login.submit'), [
        'email' => 'admin@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
            'redirect_url' => route('admin.dashboard'),
        ]);

    expect(Auth::guard('admin')->check())->toBeTrue()
        ->and(Auth::guard('admin')->id())->toBe($admin->id);
});

it('rejects login with invalid password', function (): void {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
    ]);

    $response = $this->postJson(route('admin.login.submit'), [
        'email' => 'admin@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Invalid email or password.',
        ]);

    expect(Auth::guard('admin')->check())->toBeFalse();
});

it('rejects login with non-existent email', function (): void {
    $response = $this->postJson(route('admin.login.submit'), [
        'email' => 'nonexistent@example.com',
        'password' => 'SomePassword123!',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
            'message' => 'Invalid email or password.',
        ]);

    expect(Auth::guard('admin')->check())->toBeFalse();
});

it('rejects login with 403 when admin account is inactive', function (): void {
    Admin::factory()->inactive()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    $response = $this->postJson(route('admin.login.submit'), [
        'email' => 'admin@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Your account is deactivated. Contact super admin.',
        ]);

    expect(Auth::guard('admin')->check())->toBeFalse();
});

it('rate limits admin login attempts after 5 failures', function (): void {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('CorrectPassword123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('admin.login.submit'), [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword!',
        ])->assertStatus(422);
    }

    $response = $this->postJson(route('admin.login.submit'), [
        'email' => 'admin@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertStatus(429)
        ->assertJsonStructure(['status', 'message']);
});

it('logs out authenticated admin and invalidates session', function (): void {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    expect(Auth::guard('admin')->check())->toBeFalse();
});

it('redirects unauthenticated guest accessing dashboard to login', function (): void {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

it('allows authenticated admin to view dashboard', function (): void {
    $admin = Admin::factory()->create([
        'username' => 'superboss',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertViewIs('admin.dashboard')
        ->assertSee('Welcome back, superboss!');
});

it('renders forgot password page', function (): void {
    $response = $this->get(route('admin.forgot-password'));

    $response->assertOk()
        ->assertViewIs('admin.auth.forgot-password')
        ->assertSee('Forgot Password');
});

it('sends password reset notification to active admin', function (): void {
    Notification::fake();

    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'is_active' => true,
    ]);

    $response = $this->postJson(route('admin.forgot-password.submit'), [
        'email' => 'admin@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'A password reset link has been sent to your email address.',
        ]);

    Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
});

it('rejects forgot password with 403 for inactive admin', function (): void {
    Admin::factory()->inactive()->create([
        'email' => 'admin@example.com',
    ]);

    $response = $this->postJson(route('admin.forgot-password.submit'), [
        'email' => 'admin@example.com',
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'Your account is deactivated. Contact super admin.',
        ]);
});

it('rate limits forgot password requests after 5 attempts', function (): void {
    RateLimiter::clear('admin_forgot|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('admin.forgot-password.submit'), [
            'email' => "user{$i}@example.com",
        ])->assertOk();
    }

    $response = $this->postJson(route('admin.forgot-password.submit'), [
        'email' => 'user_limit@example.com',
    ]);

    $response->assertStatus(429)
        ->assertJsonStructure(['status', 'message']);
});

it('throttles rapid repeated reset requests for the same admin account', function (): void {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'is_active' => true,
    ]);

    $this->postJson(route('admin.forgot-password.submit'), [
        'email' => 'admin@example.com',
    ])->assertOk();

    // Second immediate request for the same account is throttled by Password broker
    $this->postJson(route('admin.forgot-password.submit'), [
        'email' => 'admin@example.com',
    ])->assertStatus(429);
});

it('renders reset password page with token', function (): void {
    $response = $this->get(route('admin.reset-password', [
        'token' => 'sample-reset-token',
        'email' => 'admin@example.com',
    ]));

    $response->assertOk()
        ->assertViewIs('admin.auth.reset-password')
        ->assertSee('Set New Password')
        ->assertSee('sample-reset-token')
        ->assertSee('admin@example.com');
});

it('resets admin password with valid token', function (): void {
    $admin = Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('OldPassword123!'),
        'is_active' => true,
    ]);

    $token = Password::broker('admins')->createToken($admin);

    $response = $this->postJson(route('admin.reset-password.submit'), [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'Password reset successfully! You can now log in.',
            'redirect_url' => route('admin.login'),
        ]);

    $admin->refresh();
    expect(Hash::check('BrandNewPassword123!', $admin->password))->toBeTrue();
});

it('rejects password reset with invalid token', function (): void {
    Admin::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $response = $this->postJson(route('admin.reset-password.submit'), [
        'token' => 'completely-invalid-token',
        'email' => 'admin@example.com',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ]);
});
