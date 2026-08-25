<?php

namespace App\Console\Commands;

use App\Jobs\DispatchOutboxEvent;
use App\Support\OutboxEventStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('outbox:pump
    {--limit=50 : Maximum events to claim}
    {--lease=60 : Lease duration in seconds}
    {--consumer= : Consumer receiving the local inbox event}')]
#[Description('Reap, claim and dispatch pending outbox events after commit')]
final class PumpOutbox extends Command
{
    public function handle(OutboxEventStore $outbox): int
    {
        $worker = 'outbox-pump:'.(string) Str::uuid7();
        $consumer = trim((string) ($this->option('consumer') ?: config('queue.outbox_consumer', 'local.inbox')));
        $limit = max(1, (int) $this->option('limit'));
        $lease = max(1, (int) $this->option('lease'));
        $outbox->reapExpiredLeases($worker);
        $count = 0;

        DB::transaction(function () use ($outbox, $worker, $consumer, $limit, $lease, &$count): void {
            $events = $outbox->claim($worker, $limit, $lease);
            $count = $events->count();

            foreach ($events as $event) {
                DispatchOutboxEvent::dispatch((string) $event->event_id, $consumer, $worker)->afterCommit();
            }
        }, 5);

        $this->components->info("Claimed {$count} outbox event(s) for [{$consumer}].");

        return self::SUCCESS;
    }
}
