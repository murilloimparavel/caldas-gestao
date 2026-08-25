<?php

namespace App\Enums;

enum MembershipRoleScope: string
{
    case Tenant = 'tenant';
    case Unit = 'unit';
}
