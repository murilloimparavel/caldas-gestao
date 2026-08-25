<?php

namespace App\Enums;

enum IdempotencyStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
