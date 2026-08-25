<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First tenant bootstrap secret
    |--------------------------------------------------------------------------
    |
    | This value is read from the deployment secret store. The command never
    | accepts a password as an option, and this key is intentionally empty by
    | default so production cannot silently use a known password.
    |
    */
    'admin_password' => env('BOOTSTRAP_TENANT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Supported billing currencies
    |--------------------------------------------------------------------------
    |
    | Keep this explicit and extensible. Adding a currency requires a product
    | decision and corresponding formatting/settlement coverage.
    |
    */
    'supported_currencies' => ['BRL'],
];
