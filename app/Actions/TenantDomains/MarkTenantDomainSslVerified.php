<?php

namespace App\Actions\TenantDomains;

use App\Models\TenantDomain;

class MarkTenantDomainSslVerified
{
    public function handle(TenantDomain $domain): TenantDomain
    {
        $domain->forceFill([
            'ssl_status' => 'active',
            'ssl_verified_at' => now(),
        ])->save();

        return $domain->refresh();
    }
}
