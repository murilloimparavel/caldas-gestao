<?php

namespace App\Console\Commands;

use App\Support\Telemetry\IncidentCollector;
use Illuminate\Console\Command;
use Throwable;

final class ClaimTelemetryIncident extends Command
{
    protected $signature = 'telemetry:claim {--json : Return machine-readable JSON}';

    protected $description = 'Lease one sanitized telemetry incident for a trusted diagnostic worker';

    public function handle(IncidentCollector $collector): int
    {
        try {
            $this->line(json_encode($collector->claim(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Telemetry claim unavailable.');

            return self::FAILURE;
        }
    }
}
