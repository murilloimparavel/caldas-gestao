<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'coolify' => [
        'url' => env('COOLIFY_BASE_URL'),
        'token' => env('COOLIFY_API_KEY'),
        'application_uuid' => env('COOLIFY_APPLICATION_UUID'),
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

    'lastlink' => [
        'webhook_secret' => env('LASTLINK_WEBHOOK_SECRET'),
        'checkout_url' => env('LASTLINK_CHECKOUT_URL'),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID', '2058995828162676'),
        'conversions_api_token' => env('META_CONVERSIONS_API_TOKEN'),
        'conversions_api_version' => env('META_CONVERSIONS_API_VERSION', 'v25.0'),
        'conversions_api_url' => 'https://graph.facebook.com',
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'authorization_url' => env('GOOGLE_AUTHORIZATION_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'userinfo_url' => env('GOOGLE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo'),
        'calendar_url' => env('GOOGLE_CALENDAR_URL', 'https://www.googleapis.com/calendar/v3'),
        'scope' => env('GOOGLE_CALENDAR_SCOPE', 'https://www.googleapis.com/auth/calendar'),
        'state_ttl_minutes' => (int) env('GOOGLE_OAUTH_STATE_TTL_MINUTES', 10),
        'return_paths' => ['/calendar'],
        'timeout' => (int) env('GOOGLE_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('GOOGLE_HTTP_CONNECT_TIMEOUT', 3),
    ],

];
