<?php

namespace App\Console\Commands;

use App\Support\Telemetry\IncidentCollector;
use Illuminate\Console\Command;
use Throwable;

final class TelemetryQueueHealth extends Command
{
    protected $signature = 'telemetry:queue-health {--json}';

    protected $description = 'Read actionable queue counts without reserving or changing incidents';

    public function handle(IncidentCollector $collector): int
    {
        try {
            $this->line(json_encode($collector->queueHealth(), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Telemetry queue health unavailable.');

            return self::FAILURE;
        }
    }
}
