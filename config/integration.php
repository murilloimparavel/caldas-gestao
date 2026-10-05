<?php

return [

    'oauth' => [
        'enabled' => (bool) env('INTEGRATION_OAUTH_ENABLED', false),
        'resource' => env('INTEGRATION_OAUTH_RESOURCE'),
        'capabilities' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INTEGRATION_OAUTH_CAPABILITIES', 'context:read')),
        ))),
        'access_token_ttl_minutes' => (int) env('INTEGRATION_OAUTH_ACCESS_TOKEN_TTL_MINUTES', 15),
        'refresh_token_ttl_days' => (int) env('INTEGRATION_OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),
    ],

];
