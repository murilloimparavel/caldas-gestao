<?php

namespace App\Console\Commands;

use App\Actions\Privacy\AnonymizeExpiredCustomers as AnonymizeExpiredCustomersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retention:anonymize {--dry-run : Report eligible records without changing data} {--limit=1000 : Maximum records evaluated per policy}')]
#[Description('Anonymize customer PII after the configured retention period')]
class AnonymizeExpiredCustomers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AnonymizeExpiredCustomersAction $action): int
    {
        $summary = $action->handle(dryRun: (bool) $this->option('dry-run'), limit: max(1, (int) $this->option('limit')));
        $this->table(['metric', 'value'], collect($summary)->map(static fn (mixed $value, string $key): array => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : $value])->values()->all());

        return self::SUCCESS;
    }
}
