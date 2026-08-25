<?php

namespace App\Console\Commands;

use App\Support\InboxEventStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('inbox:reap
    {--limit=100 : Maximum leases to recover}')]
#[Description('Recover expired inbox processing leases')]
final class ReapInbox extends Command
{
    public function handle(InboxEventStore $inbox): int
    {
        $worker = 'inbox-reaper:'.(string) Str::uuid7();
        $count = $inbox->reapExpiredLeases($worker, limit: max(1, (int) $this->option('limit')));
        $this->components->info("Recovered {$count} inbox lease(s).");

        return self::SUCCESS;
    }
}
