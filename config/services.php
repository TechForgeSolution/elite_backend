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

    'firebase' => [
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'carey' => [
        'api_url' => env('CAREY_API_URL', 'https://backend-carey.tfs237.com/v1'),
        'api_key' => env('CAREY_API_KEY'),
        'app_name' => env('CAREY_APP_NAME', 'Elite 2.0'),
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

    'moneyfusion' => [
        'api_url'        => env('MONEYFUSION_API_URL', 'https://www.pay.moneyfusion.net/Elite2_0/32cb4c51bd4a466d/pay/'),
        'webhook_secret' => env('MONEYFUSION_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
    ],
];
