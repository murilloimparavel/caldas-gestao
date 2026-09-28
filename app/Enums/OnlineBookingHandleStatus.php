<?php

namespace App\Enums;

enum OnlineBookingHandleStatus: string
{
    case Reserved = 'reserved';
    case Current = 'current';
    case Redirect = 'redirect';
}
