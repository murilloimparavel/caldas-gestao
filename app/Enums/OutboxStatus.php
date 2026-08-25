<?php

namespace App\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Publishing = 'publishing';
    case Retryable = 'retryable';
    case Dead = 'dead';
    case Published = 'published';
}
