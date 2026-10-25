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

    // The old PHPRunner app, for links in notifications until a page is ported.
    // Telegram messages to drivers (PHPRunner "Αποστολή Transfers"). Off unless TELEGRAM_ENABLED=true,
    // so a copy of the app never messages real drivers.
    'telegram' => [
        'enabled' => (bool) env('TELEGRAM_ENABLED', false),
        'token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    'phprunner' => [
        'url' => env('PHPRUNNER_URL'),
        // Folder of the PHPRunner app on this server (contains `files/`), for uploads of
        // "document" fields such as driver files and fuel receipts. Empty: no uploads.
        'root' => env('PHPRUNNER_ROOT'),
    ],

];
