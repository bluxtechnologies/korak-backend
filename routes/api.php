<?php

use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\TrustedDeviceController;
use App\Http\Controllers\Api\V1\Auth\VerifyDeviceController;
use App\Http\Controllers\Api\V1\Bills\BillController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Profile\ChangePasswordController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Profile\SecurityActivityController;
use App\Http\Controllers\Api\V1\Profile\SessionController;
use App\Http\Controllers\Api\V1\Wallet\FundingController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Http\Controllers\Api\V1\Webhooks\FinityPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('register', RegisterController::class)->name('register');
                Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
                Route::post('verify-device', VerifyDeviceController::class)->middleware('throttle:otp')->name('verify-device');
                Route::post('forgot-password', ForgotPasswordController::class)->middleware('throttle:password-reset')->name('forgot-password');
                Route::post('reset-password', ResetPasswordController::class)->middleware('throttle:password-reset')->name('reset-password');

                Route::middleware('auth:customer')->group(function (): void {
                    Route::get('me', MeController::class)->name('me');
                    Route::post('logout', LogoutController::class)->name('logout');

                    Route::get('devices', [TrustedDeviceController::class, 'index'])->name('devices.index');
                    Route::delete('devices', [TrustedDeviceController::class, 'destroyAll'])->name('devices.destroy-all');
                    Route::delete('devices/{ulid}', [TrustedDeviceController::class, 'destroy'])->name('devices.destroy');
                });
            });

        Route::middleware('auth:customer')
            ->prefix('notifications')
            ->name('notifications.')
            ->group(function (): void {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::patch('{id}', [NotificationController::class, 'markAsRead'])->name('mark-read');
                Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
            });

        Route::middleware('auth:customer')
            ->prefix('profile')
            ->name('profile.')
            ->group(function (): void {
                Route::get('/', [ProfileController::class, 'show'])->name('show');
                Route::patch('/', [ProfileController::class, 'update'])->name('update');
                Route::put('password', ChangePasswordController::class)->name('password.update');
                Route::post('password', ChangePasswordController::class)->name('password.change');

                Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
                Route::delete('sessions/other', [SessionController::class, 'destroyOther'])->name('sessions.destroy-other');
                Route::delete('sessions/{id}', [SessionController::class, 'destroy'])->name('sessions.destroy');

                Route::get('security-activity', SecurityActivityController::class)->name('security-activity');
            });

        Route::middleware('auth:customer')
            ->prefix('wallet')
            ->name('wallet.')
            ->group(function (): void {
                Route::get('/', [WalletController::class, 'show'])->name('show');
                Route::post('fund', [FundingController::class, 'initiate'])->name('fund');
                Route::get('fund/{reference}', [FundingController::class, 'show'])->name('fund.show');
                Route::get('transactions', [WalletController::class, 'transactions'])->name('transactions');
            });

        // Bills & VTU (Airtime, Data, Utilities, Cable TV)
        Route::prefix('bills')
            ->name('bills.')
            ->group(function (): void {
                Route::get('categories', [BillController::class, 'categories'])->name('categories');
                Route::get('services', [BillController::class, 'services'])->name('services');
                Route::get('data-bundles', [BillController::class, 'dataBundles'])->name('data-bundles');

                Route::middleware('auth:customer')->group(function (): void {
                    Route::post('validate', [BillController::class, 'validateCustomer'])->name('validate');
                    Route::post('pay', [BillController::class, 'pay'])->name('pay');
                    Route::get('history', [BillController::class, 'history'])->name('history');
                    Route::get('{reference}', [BillController::class, 'show'])->name('show');
                });
            });

        Route::post('webhooks/cofinance', FinityPayWebhookController::class)->name('webhooks.cofinance');
        Route::post('webhooks/finitypay', FinityPayWebhookController::class)->name('webhooks.finitypay');

        Route::prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                // Admin API v1 endpoints (Doc 06).
            });
    });
