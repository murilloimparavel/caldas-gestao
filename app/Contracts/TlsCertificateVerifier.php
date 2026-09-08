<?php

namespace App\Contracts;

interface TlsCertificateVerifier
{
    public function verify(string $hostname): ?string;
}
