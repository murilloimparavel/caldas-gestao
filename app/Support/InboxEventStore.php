<?php

namespace App\Support;

use App\Enums\InboxStatus;
use App\Models\InboxEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class InboxEventStore
{
    public function __construct(private readonly PayloadGovernance $payload = new PayloadGovernance) {}

    /** @param array<string, mixed> $attributes */
    public function receive(array $attributes): InboxEvent
    {
        $receivedAt = $attributes['received_at'] ?? now();
        $payload = $this->payload->eventPayload((array) ($attributes['payload'] ?? []));
        $consumer = (string) $attributes['consumer'];
        $eventId = (string) $attributes['event_id'];
        InboxEvent::query()->insertOrIgnore([
            'tenant_id' => $attributes['tenant_id'],
            'consumer' => $consumer,
            'event_id' => $eventId,
            'event_type' => $attributes['event_type'],
            'event_version' => $attributes['event_version'] ?? 1,
            'correlation_id' => $attributes['correlation_id'] ?? null,
            'causation_id' => $attributes['causation_id'] ?? null,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => InboxStatus::Received->value,
            'attempts' => 0,
            'received_at' => $receivedAt,
            'available_at' => $attributes['available_at'] ?? $receivedAt,
            'created_at' => $attributes['created_at'] ?? $receivedAt,
            'updated_at' => $attributes['updated_at'] ?? $receivedAt,
        ]);

        $existing = InboxEvent::query()
            ->where('consumer', $consumer)
            ->where('event_id', $eventId)
            ->firstOrFail();

        $sameEnvelope = $existing->tenant_id === (string) $attributes['tenant_id']
            && $existing->event_type === (string) $attributes['event_type']
            && $existing->event_version === (int) ($attributes['event_version'] ?? 1)
            && $existing->correlation_id === ($attributes['correlation_id'] ?? null)
            && $existing->causation_id === ($attributes['causation_id'] ?? null)
            && $this->payload->canonicalHash($existing->payload) === $this->payload->canonicalHash($payload);

        if (! $sameEnvelope) {
            throw new \LogicException('The inbox event envelope conflicts with the existing consumer delivery.');
        }

        return $existing;
    }

    /** @return Collection<int, InboxEvent> */
    public function claim(string $consumer, string $worker, int $limit = 50, int $leaseSeconds = 60): Collection
    {
        $this->assertWorker($worker);

        return DB::transaction(function () use ($consumer, $worker, $limit, $leaseSeconds): Collection {
            $now = now();
            $query = InboxEvent::query()
                ->where('consumer', $consumer)
                ->whereIn('status', [InboxStatus::Received->value, InboxStatus::Retryable->value])
                ->where('available_at', '<=', $now)
                ->orderBy('id')
                ->limit(max(1, $limit));
            $events = DB::getDriverName() === 'pgsql'
                ? $query->lock('FOR UPDATE SKIP LOCKED')->get()
                : $query->lockForUpdate()->get();
            $leaseUntil = $now->copy()->addSeconds(max(1, $leaseSeconds));
            $claimed = new Collection;

            foreach ($events as $event) {
                $updated = InboxEvent::query()
                    ->whereKey($event->getKey())
                    ->where('consumer', $consumer)
                    ->whereIn('status', [InboxStatus::Received->value, InboxStatus::Retryable->value])
                    ->where('available_at', '<=', $now)
                    ->update([
                        'status' => InboxStatus::Processing->value,
                        'locked_by' => $worker,
                        'locked_at' => $now,
                        'lease_until' => $leaseUntil,
                        'last_attempt_at' => $now,
                        'attempts' => $event->attempts + 1,
                    ]);

                if ($updated === 1) {
                    $claimed->push(InboxEvent::query()->findOrFail($event->getKey()));
                }
            }

            return $claimed;
        }, 5);
    }

    public function markProcessed(InboxEvent|int|string $event, string $worker): InboxEvent
    {
        $this->assertWorker($worker);
        $id = $this->id($event);
        $updated = InboxEvent::query()
            ->whereKey($id)
            ->where('status', InboxStatus::Processing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => InboxStatus::Processed->value,
                'processed_at' => now(),
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
            ]);

        return $this->transitionResult($id, $updated, 'process');
    }

    public function markRetryable(InboxEvent|int|string $event, string $worker, ?string $error = null, int $maxAttempts = 5): InboxEvent
    {
        $this->assertWorker($worker);
        $model = $this->resolve($event);

        if ($model->attempts >= $maxAttempts) {
            return $this->markDead($model, $worker, $error);
        }

        $updated = InboxEvent::query()
            ->whereKey($model->getKey())
            ->where('status', InboxStatus::Processing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => InboxStatus::Retryable->value,
                'available_at' => now()->addSeconds(min(3600, 2 ** min(10, max(0, $model->attempts - 1)))),
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
                'last_error' => $error === null ? null : mb_substr($error, 0, 2000),
            ]);

        return $this->transitionResult($model->getKey(), $updated, 'retry');
    }

    public function markFailed(InboxEvent|int|string $event, string $worker, ?string $error = null): InboxEvent
    {
        $this->assertWorker($worker);
        $id = $this->id($event);
        $updated = InboxEvent::query()
            ->whereKey($id)
            ->where('status', InboxStatus::Processing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => InboxStatus::Failed->value,
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
                'last_error' => $error === null ? null : mb_substr($error, 0, 2000),
            ]);

        return $this->transitionResult($id, $updated, 'fail');
    }

    public function markDead(InboxEvent|int|string $event, string $worker, ?string $error = null): InboxEvent
    {
        $this->assertWorker($worker);
        $id = $this->id($event);
        $updated = InboxEvent::query()
            ->whereKey($id)
            ->where('status', InboxStatus::Processing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => InboxStatus::Dead->value,
                'dead_at' => now(),
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
                'last_error' => $error === null ? null : mb_substr($error, 0, 2000),
            ]);

        return $this->transitionResult($id, $updated, 'dead-letter');
    }

    public function reapExpiredLeases(string $reaper, int $maxAttempts = 5, int $limit = 100): int
    {
        $this->assertWorker($reaper);

        return DB::transaction(function () use ($maxAttempts, $limit): int {
            $now = now();
            $query = InboxEvent::query()
                ->where('status', InboxStatus::Processing->value)
                ->whereNotNull('lease_until')
                ->where('lease_until', '<=', $now)
                ->orderBy('id')
                ->limit(max(1, $limit));
            $events = DB::getDriverName() === 'pgsql'
                ? $query->lock('FOR UPDATE SKIP LOCKED')->get()
                : $query->lockForUpdate()->get();
            $count = 0;

            foreach ($events as $event) {
                $terminal = $event->attempts >= $maxAttempts;
                $updated = InboxEvent::query()
                    ->whereKey($event->getKey())
                    ->where('status', InboxStatus::Processing->value)
                    ->where('locked_by', $event->locked_by)
                    ->where('lease_until', '<=', $now)
                    ->update($terminal ? [
                        'status' => InboxStatus::Dead->value,
                        'dead_at' => $now,
                        'locked_by' => null,
                        'locked_at' => null,
                        'lease_until' => null,
                        'last_error' => 'Inbox lease expired.',
                    ] : [
                        'status' => InboxStatus::Retryable->value,
                        'available_at' => $now->copy()->addSeconds(min(3600, 2 ** min(10, max(0, $event->attempts - 1)))),
                        'locked_by' => null,
                        'locked_at' => null,
                        'lease_until' => null,
                        'last_error' => 'Inbox lease expired.',
                    ]);
                $count += $updated;
            }

            return $count;
        }, 5);
    }

    public function isLeaseValid(InboxEvent|int|string $event, string $worker): bool
    {
        $this->assertWorker($worker);

        return InboxEvent::query()
            ->whereKey($this->id($event))
            ->where('status', InboxStatus::Processing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->exists();
    }

    private function resolve(InboxEvent|int|string $event): InboxEvent
    {
        return $event instanceof InboxEvent ? ($event->fresh() ?? throw new \RuntimeException('Inbox event no longer exists.')) : InboxEvent::query()->findOrFail($event);
    }

    private function id(InboxEvent|int|string $event): int|string
    {
        return $event instanceof InboxEvent ? $event->getKey() : $event;
    }

    private function transitionResult(int|string $id, int $updated, string $transition): InboxEvent
    {
        if ($updated !== 1) {
            throw new \LogicException("Unable to {$transition} inbox event: lease or status is no longer valid.");
        }

        return InboxEvent::query()->findOrFail($id);
    }

    private function assertWorker(string $worker): void
    {
        if (trim($worker) === '') {
            throw new \InvalidArgumentException('A non-empty inbox worker token is required.');
        }
    }
}
