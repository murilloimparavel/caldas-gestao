<?php

namespace App\Support\Performance;

final class QueryMetricsCollector
{
    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    public function reset(): void
    {
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
    }

    public function record(float|int $timeMs): void
    {
        $this->queryCount++;
        $this->queryTimeMs += (float) $timeMs;
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function queryTimeMs(): float
    {
        return round($this->queryTimeMs, 3);
    }
}
