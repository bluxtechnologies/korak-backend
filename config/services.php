<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'finitypay' => [
        'env' => env('FINITYPAY_ENV') ?: (env('COFINANCE_ENV') ?: 'production'),
        'base_url' => str_replace('api.cofinance.ng', 'api.finitypay.com.ng', (string) (env('FINITYPAY_BASE_URL') ?: (env('COFINANCE_BASE_URL') ?: 'https://api.finitypay.com.ng/v1'))),
        'business_id' => env('FINITYPAY_BUSINESS_ID') ?: env('COFINANCE_BUSINESS_ID'),
        'public_key' => env('FINITYPAY_PUBLIC_KEY') ?: env('COFINANCE_PUBLIC_KEY'),
        'secret_key' => env('FINITYPAY_SECRET_KEY') ?: env('COFINANCE_SECRET_KEY'),
        'cofinance_secret_key' => env('COFINANCE_SECRET_KEY'),
        'webhook_secret' => env('FINITYPAY_WEBHOOK_SECRET') ?: env('COFINANCE_WEBHOOK_SECRET'),
        'cofinance_webhook_secret' => env('COFINANCE_WEBHOOK_SECRET'),
        'skip_webhook_verify' => filter_var(env('FINITYPAY_SKIP_WEBHOOK_VERIFY', env('COFINANCE_SKIP_WEBHOOK_VERIFY', false)), FILTER_VALIDATE_BOOLEAN),
        'mock' => filter_var(env('MOCK_PAYMENT_PROVIDER', env('FINITYPAY_MOCK', false)), FILTER_VALIDATE_BOOLEAN),
        'bank_partner' => env('FINITYPAY_BANK_PARTNER'),
        'timeout' => 25,
    ],

];
