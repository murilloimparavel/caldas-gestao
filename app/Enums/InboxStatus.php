<?php

namespace App\Enums;

enum InboxStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Retryable = 'retryable';
    case Processed = 'processed';
    case Failed = 'failed';
    case Dead = 'dead';
}
