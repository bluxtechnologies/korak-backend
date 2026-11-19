<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Set SANCTUM_STATEFUL_DOMAINS to a comma-
    | separated list of your SPA origins (no trailing slashes).
    |
    | Local defaults include localhost:3000 (Next.js dev server).
    | Production must list the exact web subdomain, e.g. app.korak.com
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8070,::1,www.korakdigital.com,korakdigital.com',
        Sanctum::currentApplicationUrlWithPort(),
        env('FRONTEND_URL') ? ','.parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Guards that Sanctum checks for stateful session authentication.
    | We use the 'web' guard (which covers both customers and admins via
    | their respective providers) for SPA cookie auth. Bearer-token
    | authentication is intentionally NOT used (Doc 04 — locked).
    |
    */

    'guard' => ['customer', 'admin', 'web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Null = session-bound (expires when session expires). Session lifetime
    | is controlled by SESSION_LIFETIME. First-party SPA sessions are
    | not affected by token expiration.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Only relevant if API tokens are issued in the future.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware Sanctum uses during stateful SPA request processing.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
