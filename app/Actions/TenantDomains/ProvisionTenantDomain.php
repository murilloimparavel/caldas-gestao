<?php

namespace App\Actions\TenantDomains;

use App\Contracts\CustomDomainProvisioner;
use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use DomainException;

final class ProvisionTenantDomain
{
    public function __construct(private readonly CustomDomainProvisioner $provisioner) {}

    public function handle(TenantDomain $domain): TenantDomain
    {
        if ($domain->status !== TenantDomainStatus::Verified) {
            throw new DomainException('O domínio precisa ser verificado antes do provisionamento.');
        }

        $this->provisioner->provision($domain);

        return $domain->refresh();
    }
}
