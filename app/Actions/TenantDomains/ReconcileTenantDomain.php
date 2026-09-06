<?php

namespace App\Actions\TenantDomains;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;

class ReconcileTenantDomain
{
    public function __construct(
        private readonly VerifyTenantDomain $verify,
        private readonly ProvisionTenantDomain $provision,
        private readonly VerifyTenantDomainSsl $verifySsl,
        private readonly ActivateTenantDomain $activate,
    ) {}

    public function handle(TenantDomain $domain): TenantDomain
    {
        $domain = $domain->refresh();

        if ($domain->status === TenantDomainStatus::Pending) {
            $domain = $this->verify->handle($domain);
        }

        if ($domain->status !== TenantDomainStatus::Verified) {
            return $domain;
        }

        $this->provision->handle($domain);
        $domain = $this->verifySsl->handle($domain);

        if ($domain->ssl_status !== 'active') {
            return $domain;
        }

        return $this->activate->handle($domain->refresh());
    }
}
