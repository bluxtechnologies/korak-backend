<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Webhooks\FinityPayWebhookController;
use App\Models\Customer;
use App\Notifications\DeviceVerificationOtpNotification;
use App\Notifications\DeviceVerifiedNotification;
use App\Notifications\LockoutNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Korak API is running.',
    ]);
});

// FinityPay / CoFinance Webhook endpoints at root (matching dashboard configuration)
Route::post('webhooks/cofinance', FinityPayWebhookController::class)->name('webhooks.cofinance.root');
Route::post('webhooks/finitypay', FinityPayWebhookController::class)->name('webhooks.finitypay.root');

// Developer local mail previews (Visually inspect templates in browser)
if (app()->environment('local')) {
    Route::prefix('dev/mail')->group(function (): void {
        Route::get('verify-device', function () {
            $customer = new Customer(['username' => 'alice', 'email' => 'alice@example.com']);
            $notification = new DeviceVerificationOtpNotification(
                code: '849201',
                deviceName: 'Chrome on macOS Sonoma',
                ipAddress: '192.168.1.42'
            );

            return $notification->toMail($customer)->render();
        });

        Route::get('reset-password', function () {
            $customer = new Customer(['username' => 'alice', 'email' => 'alice@example.com']);
            $notification = new ResetPasswordNotification(token: 'demo-sample-token-64-characters');

            return $notification->toMail($customer)->render();
        });

        Route::get('lockout', function () {
            $customer = new Customer(['username' => 'alice', 'email' => 'alice@example.com']);
            $notification = new LockoutNotification(durationMinutes: 15);

            return $notification->toMail($customer)->render();
        });

        Route::get('password-changed', function () {
            $customer = new Customer(['username' => 'alice', 'email' => 'alice@example.com']);
            $notification = new PasswordChangedNotification;

            return $notification->toMail($customer)->render();
        });

        Route::get('device-verified', function () {
            $customer = new Customer(['username' => 'alice', 'email' => 'alice@example.com']);
            $notification = new DeviceVerifiedNotification(
                deviceName: 'Safari on iPhone 16 Pro',
                ipAddress: '192.168.1.88'
            );

            return $notification->toMail($customer)->render();
        });
    });
}

Route::prefix('admininistator')->group(function (): void {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });

    Route::middleware('guest:admin')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
        Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login.submit');

        Route::get('forgot-password', [AdminAuthController::class, 'showForgotPasswordForm'])->name('admin.forgot-password');
        Route::post('forgot-password', [AdminAuthController::class, 'sendResetLinkEmail'])->name('admin.forgot-password.submit');

        Route::get('reset-password/{token}', [AdminAuthController::class, 'showResetPasswordForm'])->name('admin.reset-password');
        Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])->name('admin.reset-password.submit');
    });

    Route::middleware('auth:admin')->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    });
});
