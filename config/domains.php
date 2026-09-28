<?php

return [
    'shared_booking_host' => strtolower(trim((string) env('DOMAINS_SHARED_BOOKING_HOST', 'barber.caldasindica.com'))),
    'expected_cname' => env('DOMAINS_EXPECTED_CNAME', 'vps.caldasindica.com'),
    'official_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('DOMAINS_OFFICIAL_HOSTS', '')),
    ))),
    'ssl_timeout' => (int) env('DOMAINS_SSL_TIMEOUT', 8),
];
