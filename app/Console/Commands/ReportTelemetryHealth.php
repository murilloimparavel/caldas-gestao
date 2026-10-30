<?php

namespace App\Console\Commands;

use App\Support\Telemetry\RuntimeHealth;
use Illuminate\Console\Command;
use Throwable;

final class ReportTelemetryHealth extends Command
{
    protected $signature = 'telemetry:report-health {--json}';

    protected $description = 'Accept one bounded typed runtime health snapshot on standard input';

    public function handle(RuntimeHealth $health): int
    {
        try {
            $input = stream_get_contents(STDIN, 16385);
            if ($input === false || strlen($input) > 16384) {
                throw new \InvalidArgumentException('Invalid health payload size.');
            }
            $payload = json_decode($input, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || array_is_list($payload)) {
                throw new \InvalidArgumentException('Expected a health object.');
            }
            $this->line(json_encode($health->report($payload), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Invalid or unavailable runtime health snapshot.');

            return self::FAILURE;
        }
    }
}
