<?php

namespace App\Actions\TenantDomains;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;

class DisableTenantDomain
{
    public function handle(TenantDomain $domain): TenantDomain
    {
        $domain->forceFill(['status' => TenantDomainStatus::Disabled, 'disabled_at' => now()])->save();

        return $domain->refresh();
    }
}
