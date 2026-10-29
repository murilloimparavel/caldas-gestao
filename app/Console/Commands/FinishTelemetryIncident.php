<?php

namespace App\Console\Commands;

use App\Support\Telemetry\IncidentCollector;
use Illuminate\Console\Command;
use Throwable;

final class FinishTelemetryIncident extends Command
{
    protected $signature = 'telemetry:finish {id} {--claim= : Exact lease UUID} {--status= : completed, failed, or blocked} {--json : Return machine-readable JSON}';

    protected $description = 'Acknowledge an incident only with its current unexpired claim token';

    public function handle(IncidentCollector $collector): int
    {
        $id = (string) $this->argument('id');
        $claim = (string) $this->option('claim');
        $result = (string) $this->option('status');
        try {
            if (! $collector->finish($id, $claim, $result)) {
                $this->error('Invalid or expired telemetry claim.');

                return self::FAILURE;
            }
            $this->line(json_encode(['id' => $id, 'status' => $result === 'completed' ? 'completed' : 'failed', 'result' => $result], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Telemetry acknowledgement unavailable.');

            return self::FAILURE;
        }
    }
}
