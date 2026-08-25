<?php

namespace App\Jobs;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use App\Support\InboxEventStore;
use App\Support\OutboxEventStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DispatchOutboxEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $eventId, public readonly string $consumer, public readonly string $workerToken)
    {
        if (trim($workerToken) === '') {
            throw new \InvalidArgumentException('An outbox worker token is required.');
        }

        $this->onQueue('outbox');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->eventId.':'.$this->consumer.':'.$this->workerToken;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 30, 120, 600];
    }

    public function handle(OutboxEventStore $outbox, InboxEventStore $inbox): void
    {
        DB::transaction(function () use ($outbox, $inbox): void {
            $event = OutboxEvent::query()
                ->where('event_id', $this->eventId)
                ->lockForUpdate()
                ->first();

            if ($event === null || $event->getRawOriginal('status') !== OutboxStatus::Publishing->value) {
                return;
            }

            if (! $outbox->isLeaseValid($event, $this->workerToken)) {
                throw new \LogicException('The outbox event lease is no longer valid.');
            }

            $inbox->receive([
                'tenant_id' => $event->tenant_id,
                'consumer' => $this->consumer,
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'event_version' => $event->event_version,
                'correlation_id' => $event->correlation_id,
                'causation_id' => $event->causation_id,
                'payload' => $event->payload,
            ]);

            $outbox->markPublished($event, $this->workerToken);
        }, 5);
    }

    public function failed(?Throwable $exception): void
    {
        $event = OutboxEvent::query()->where('event_id', $this->eventId)->first();

        if ($event !== null) {
            try {
                app(OutboxEventStore::class)->markRetryable($event, $this->workerToken, $exception?->getMessage());
            } catch (\LogicException) {
                // The lease may have been recovered by the reaper already.
            }
        }
    }
}
