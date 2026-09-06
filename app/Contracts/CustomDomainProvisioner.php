<?php

namespace App\Contracts;

use App\Models\TenantDomain;

interface CustomDomainProvisioner
{
    public function provision(TenantDomain $domain): void;
}
