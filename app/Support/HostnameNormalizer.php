<?php

namespace App\Support;

use InvalidArgumentException;

class HostnameNormalizer
{
    public static function normalize(string $hostname): string
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));

        if ($hostname === '' || strlen($hostname) > 253 || str_contains($hostname, '://') || str_contains($hostname, '/') || str_contains($hostname, ':')) {
            throw new InvalidArgumentException('Hostname inválido.');
        }

        $labels = explode('.', $hostname);

        if (count($labels) < 2 || in_array('', $labels, true)) {
            throw new InvalidArgumentException('Hostname inválido.');
        }

        foreach ($labels as $label) {
            if (strlen($label) > 63 || ! preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $label)) {
                throw new InvalidArgumentException('Hostname inválido.');
            }
        }

        return $hostname;
    }
}
