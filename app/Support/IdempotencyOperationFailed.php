<?php

namespace App\Support;

use Throwable;

final class IdempotencyOperationFailed extends \RuntimeException
{
    public function __construct(public readonly Throwable $operationException)
    {
        parent::__construct($operationException->getMessage(), (int) $operationException->getCode(), $operationException);
    }
}
