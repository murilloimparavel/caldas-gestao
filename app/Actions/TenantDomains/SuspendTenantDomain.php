<?php

namespace App\Actions\TenantDomains;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;

class SuspendTenantDomain
{
    public function handle(TenantDomain $domain): TenantDomain
    {
        $domain->forceFill(['status' => TenantDomainStatus::Suspended])->save();

        return $domain->refresh();
    }
}
