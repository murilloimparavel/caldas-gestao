<?php

$proxies = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

foreach ($proxies as $proxy) {
    [$ip, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);
    $ipVersion = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? 4
        : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : null);
    $maximumPrefix = $ipVersion === 4 ? 32 : ($ipVersion === 6 ? 128 : null);

    if (
        $ipVersion === null ||
        ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix < 1 || (int) $prefix > $maximumPrefix))
    ) {
        throw new InvalidArgumentException('TRUSTED_PROXIES must contain only valid IP addresses or CIDRs.');
    }
}

return [
    'proxies' => $proxies,
];
