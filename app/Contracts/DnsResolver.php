<?php

namespace App\Contracts;

interface DnsResolver
{
    /** @return list<array<string, mixed>> */
    public function records(string $hostname, int $type): array;
}
