<?php

use App\Http\Controllers\Api\V1\Admin\AdminSupportTicketController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendDeviceCodeController;
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
use App\Http\Controllers\Api\V1\Support\SupportTicketController;
use App\Http\Controllers\Api\V1\Wallet\DepositController;
use App\Http\Controllers\Api\V1\Wallet\FundingController;
use App\Http\Controllers\Api\V1\Wallet\VirtualAccountController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use App\Http\Controllers\Api\V1\Webhooks\FinityPayWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('health', HealthController::class)->name('health');

        Route::get('diagnostics', function () {
            $hasSessions = false;
            $hasCustomers = false;
            $hasWallets = false;
            try {
                $hasSessions = Schema::hasTable('sessions');
                $hasCustomers = Schema::hasTable('customers');
                $hasWallets = Schema::hasTable('wallets');
            } catch (Throwable) {
                // Ignore DB error for diagnostics check
            }

            return response()->json([
                'status' => 'ok',
                'app_key_set' => ! empty(config('app.key')),
                'session_driver' => config('session.driver'),
                'session_domain' => config('session.domain'),
                'cors_origins' => config('cors.allowed_origins'),
                'sanctum_stateful' => config('sanctum.stateful'),
                'database' => [
                    'sessions_table' => $hasSessions,
                    'customers_table' => $hasCustomers,
                    'wallets_table' => $hasWallets,
                ],
                'storage_sessions_writable' => is_writable(storage_path('framework/sessions')),
            ]);
        })->name('diagnostics');

        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post('register', RegisterController::class)->name('register');
                Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
                Route::post('verify-device', VerifyDeviceController::class)->middleware('throttle:otp')->name('verify-device');
                Route::match(['GET', 'POST'], 'verify-device/resend', ResendDeviceCodeController::class)->middleware('throttle:otp')->name('verify-device.resend');
                Route::match(['GET', 'POST'], 'resend-device-code', ResendDeviceCodeController::class)->middleware('throttle:otp')->name('resend-device-code');
                Route::match(['GET', 'POST'], 'resend-otp', ResendDeviceCodeController::class)->middleware('throttle:otp')->name('resend-otp');
                Route::match(['GET', 'POST'], 'verify-device/resend-otp', ResendDeviceCodeController::class)->middleware('throttle:otp')->name('verify-device.resend-otp');
                Route::match(['GET', 'POST'], 'resend-verification-code', ResendDeviceCodeController::class)->middleware('throttle:otp')->name('resend-verification-code');
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
                Route::get('dedicated-account', [VirtualAccountController::class, 'show'])->name('dedicated-account.show');
                Route::post('dedicated-account', [VirtualAccountController::class, 'store'])->name('dedicated-account.store');
                Route::get('transactions', [WalletController::class, 'transactions'])->name('transactions');

                // Deposit Modal & Fee Endpoints
                Route::prefix('deposit')->name('deposit.')->group(function (): void {
                    Route::get('tiers', [DepositController::class, 'tiers'])->name('tiers');
                    Route::post('calculate', [DepositController::class, 'calculate'])->name('calculate');
                    Route::post('initiate', [DepositController::class, 'initiate'])->name('initiate');
                });
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

        // Support Tickets (Customer)
        Route::middleware('auth:customer')
            ->prefix('support/tickets')
            ->name('support.tickets.')
            ->group(function (): void {
                Route::get('/', [SupportTicketController::class, 'index'])->name('index');
                Route::post('/', [SupportTicketController::class, 'store'])->name('store');
                Route::get('{id}', [SupportTicketController::class, 'show'])->name('show');
                Route::post('{id}/messages', [SupportTicketController::class, 'reply'])->name('messages.store');
            });

        Route::prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                Route::prefix('support/tickets')
                    ->name('support.tickets.')
                    ->group(function (): void {
                        Route::get('/', [AdminSupportTicketController::class, 'index'])->name('index');
                        Route::get('{id}', [AdminSupportTicketController::class, 'show'])->name('show');
                        Route::patch('{id}/status', [AdminSupportTicketController::class, 'updateStatus'])->name('update-status');
                        Route::post('{id}/messages', [AdminSupportTicketController::class, 'reply'])->name('messages.store');
                    });
            });
    });
