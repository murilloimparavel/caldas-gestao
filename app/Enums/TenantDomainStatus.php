<?php

namespace App\Enums;

enum TenantDomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Active = 'active';
    case Disabled = 'disabled';
    case Suspended = 'suspended';
}
