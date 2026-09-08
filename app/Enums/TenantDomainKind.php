<?php

namespace App\Enums;

enum TenantDomainKind: string
{
    case Management = 'management';
    case Public = 'public';
}
