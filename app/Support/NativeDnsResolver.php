<?php

namespace App\Support;

use App\Contracts\DnsResolver;

final class NativeDnsResolver implements DnsResolver
{
    /** @return list<array<string, mixed>> */
    public function records(string $hostname, int $type): array
    {
        $records = dns_get_record($hostname, $type);

        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }
}
