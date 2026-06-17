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

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'http_verify' => env('FCM_HTTP_VERIFY', true),
        'ca_file' => env('FCM_CA_FILE'),
    ],

    'face_verification' => [
        'url' => env('FACE_SERVICE_URL', 'http://127.0.0.1:5001'),
        'timeout' => env('FACE_SERVICE_TIMEOUT', 15),
    ],

    'daily' => [
        'url' => env('DAILY_APP_URL', 'http://127.0.0.1:8001'),
        'internal_secret' => env('DAILY_INTERNAL_SECRET'),
    ],

    'whatsapp' => [
        'endpoint' => env('WHATSAPP_ENDPOINT', 'http://72.60.78.159:3000/client/sendMessage/beacon'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'timeout' => env('WHATSAPP_TIMEOUT', 15),
    ],

];
