<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Safety fallback: if database session driver is configured but sessions table is not migrated yet,
        // fall back to cookie sessions instead of crashing with a 500 error on web requests.
        if (config('session.driver') === 'database' && ! app()->runningInConsole()) {
            try {
                if (! Schema::hasTable(config('session.table', 'sessions'))) {
                    config(['session.driver' => 'cookie']);
                }
            } catch (\Throwable) {
                config(['session.driver' => 'cookie']);
            }
        }
    }

    /**
     * Configure named rate limiters per Doc 06 §9 and Doc 08 §2.
     */
    protected function configureRateLimiting(): void
    {
        // General API baseline limiter (60 req/min per user or IP).
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip()
            );
        });

        // Login endpoint limiter (5 attempts per minute per identifier + IP, Doc 08 §2 & Doc 22 B6.1).
        RateLimiter::for('login', function (Request $request): Limit {
            $identifier = (string) ($request->input('login') ?: $request->input('email') ?: $request->input('username') ?: '');
            $key = $identifier !== ''
                ? Str::transliterate(Str::lower($identifier).'|'.$request->ip())
                : (string) $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Password reset limiter (5 attempts per minute per email or IP, Doc 08 §2).
        RateLimiter::for('password-reset', function (Request $request): Limit {
            $identifier = (string) ($request->input('email') ?: $request->ip());

            return Limit::perMinute(5)->by($identifier);
        });

        // OTP verification limiter (3 attempts per minute per challenge or IP, Doc 08 §2).
        RateLimiter::for('otp', function (Request $request): Limit {
            $identifier = (string) ($request->input('challenge_token') ?: $request->user()?->getAuthIdentifier() ?: '');
            $key = $identifier !== ''
                ? Str::transliterate(Str::lower($identifier).'|'.$request->ip())
                : (string) $request->ip();

            return Limit::perMinute(3)->by($key);
        });

        // Money-moving endpoint limiter (10 requests per minute per user, Doc 06).
        RateLimiter::for('transactions', function (Request $request): Limit {
            return Limit::perMinute(10)->by(
                $request->user()?->getAuthIdentifier() ?: $request->ip()
            );
        });

        // Public health endpoint limiter (120 requests per minute per IP for probes).
        RateLimiter::for('health', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
