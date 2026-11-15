<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    |
    | Doc 04 requirements:
    |   - supports_credentials = true  (cookies must travel with requests)
    |   - allowed_origins must be an explicit list; never '*' when credentials
    |     are involved (browsers reject wildcard + credentials)
    |   - Both /api/* and /sanctum/csrf-cookie are CORS-enabled paths
    |
    | Set CORS_ALLOWED_ORIGINS in .env as a comma-separated list, e.g.:
    |   Local:      http://localhost:3000
    |   Production: https://app.korak.com
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Expose X-Request-ID so the frontend can log it for correlation.
    'exposed_headers' => ['X-Request-ID'],

    // 2 hours — browsers cache pre-flight results for this duration.
    'max_age' => 7200,

    // Required for Sanctum SPA cookie auth.
    'supports_credentials' => true,

];
