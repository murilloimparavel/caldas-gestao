<?php

namespace App\Enums;

enum EntitlementSource: string
{
    case Plan = 'plan';
    case Trial = 'trial';
    case Manual = 'manual';
    case Integration = 'integration';
}
