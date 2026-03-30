<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'mollie' => [
        'api_key' => env('MOLLIE_API_KEY'),          // Live API key
        'test_api_key' => env('MOLLIE_TEST_API_KEY'), // Test API key
        'profile_id' => env('MOLLIE_PROFILE_ID'),    // Profile ID
        'use_test' => env('MOLLIE_USE_TEST', true),
        'currency' => env('MOLLIE_CURRENCY', 'EUR'),
        'locale' => env('MOLLIE_LOCALE', 'en_US'),
        'webhook_url' => env('MOLLIE_WEBHOOK_URL'),
    ],

];
