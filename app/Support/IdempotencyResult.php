<?php

namespace App\Support;

use App\Models\IdempotencyKey;

final readonly class IdempotencyResult
{
    public function __construct(
        public mixed $value,
        public bool $replayed,
        public IdempotencyKey $key,
        public int $responseCode = 200,
    ) {}
}
