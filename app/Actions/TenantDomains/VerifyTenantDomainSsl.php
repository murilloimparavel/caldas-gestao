<?php

namespace App\Actions\TenantDomains;

use App\Contracts\TlsCertificateVerifier;
use App\Models\TenantDomain;
use Illuminate\Support\Carbon;

class VerifyTenantDomainSsl
{
    public function __construct(private readonly TlsCertificateVerifier $certificates) {}

    public function handle(TenantDomain $domain): TenantDomain
    {
        $error = $this->certificates->verify($domain->hostname);
        $verifiedAt = Carbon::now();

        $domain->forceFill([
            'ssl_status' => $error === null ? 'active' : 'error',
            'ssl_verified_at' => $error === null ? $verifiedAt : null,
            'last_dns_error' => $error,
        ])->save();

        return $domain->refresh();
    }
}
