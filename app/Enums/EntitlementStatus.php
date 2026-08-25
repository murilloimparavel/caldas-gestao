<?php

namespace App\Enums;

enum EntitlementStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace, self::Suspended], true);
    }
}
