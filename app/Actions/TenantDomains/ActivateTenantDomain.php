<?php

namespace App\Actions\TenantDomains;

use App\Enums\TenantDomainStatus;
use App\Models\TenantDomain;
use DomainException;

class ActivateTenantDomain
{
    public function handle(TenantDomain $domain): TenantDomain
    {
        if ($domain->status !== TenantDomainStatus::Verified) {
            throw new DomainException('O domínio precisa ser verificado antes da ativação.');
        }

        if ($domain->ssl_status !== 'active') {
            throw new DomainException('O certificado SSL precisa estar ativo antes da ativação.');
        }

        $domain->forceFill([
            'status' => TenantDomainStatus::Active,
            'activated_at' => now(),
        ])->save();

        return $domain->refresh();
    }
}
