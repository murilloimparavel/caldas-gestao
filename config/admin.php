<?php

use App\Support\CanonicalEmail;

return [
    'super_admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => CanonicalEmail::normalize($email),
        explode(',', (string) env('SUPER_ADMIN_EMAILS', '')),
    ))),
];
