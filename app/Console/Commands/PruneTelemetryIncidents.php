<?php

namespace App\Console\Commands;

use App\Support\Telemetry\IncidentCollector;
use Illuminate\Console\Command;
use Throwable;

final class PruneTelemetryIncidents extends Command
{
    protected $signature = 'telemetry:prune';

    protected $description = 'Prune sanitized telemetry with bounded retention and record count';

    public function handle(IncidentCollector $collector): int
    {
        try {
            $this->line(json_encode(['deleted' => $collector->prune()], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Telemetry pruning unavailable.');

            return self::FAILURE;
        }
    }
}
