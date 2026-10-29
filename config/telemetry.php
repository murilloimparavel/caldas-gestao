<?php

return [
    'enabled' => env('TELEMETRY_ENABLED', env('APP_ENV') === 'production'),
    'release' => env('APP_RELEASE', env('APP_VERSION', 'unknown')),
    'probe' => env('TELEMETRY_PROBE', false),
    'retention_days' => 30,
    'max_records' => 1000,
    'browser_threshold' => 3,
    'lease_minutes' => 60,
    'max_attempts' => 2,
    'per_ip_per_minute' => 10,
    'global_per_minute' => 100,
];
