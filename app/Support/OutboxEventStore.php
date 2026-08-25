<?php

namespace App\Support;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OutboxEventStore
{
    public function __construct(private readonly PayloadGovernance $payload = new PayloadGovernance) {}

    /** @param array<string, mixed> $attributes */
    public function enqueue(array $attributes): OutboxEvent
    {
        $occurredAt = $attributes['occurred_at'] ?? now();

        return OutboxEvent::query()->create([
            'event_id' => $attributes['event_id'] ?? (string) Str::uuid7(),
            'tenant_id' => $attributes['tenant_id'],
            'unit_id' => $attributes['unit_id'] ?? null,
            'actor_user_id' => $attributes['actor_user_id'] ?? null,
            'aggregate_type' => $attributes['aggregate_type'],
            'aggregate_id' => $attributes['aggregate_id'],
            'aggregate_version' => max(1, (int) ($attributes['aggregate_version'] ?? 1)),
            'event_type' => $attributes['event_type'],
            'event_version' => $attributes['event_version'] ?? 1,
            'correlation_id' => $attributes['correlation_id'] ?? null,
            'causation_id' => $attributes['causation_id'] ?? null,
            'payload' => $this->payload->eventPayload((array) ($attributes['payload'] ?? [])),
            'status' => $attributes['status'] ?? OutboxStatus::Pending,
            'occurred_at' => $occurredAt,
            'available_at' => $attributes['available_at'] ?? $occurredAt,
            'attempts' => 0,
            'created_at' => $attributes['created_at'] ?? $occurredAt,
        ]);
    }

    /** @return Collection<int, OutboxEvent> */
    public function claim(string $worker, int $limit = 50, int $leaseSeconds = 60): Collection
    {
        $this->assertWorker($worker);

        return DB::transaction(function () use ($worker, $limit, $leaseSeconds): Collection {
            $now = now();
            $query = OutboxEvent::query()
                ->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Available->value, OutboxStatus::Retryable->value])
                ->where('available_at', '<=', $now)
                ->orderBy('id')
                ->limit(max(1, $limit));

            $events = DB::getDriverName() === 'pgsql'
                ? $query->lock('FOR UPDATE SKIP LOCKED')->get()
                : $query->lockForUpdate()->get();
            $leaseUntil = $now->copy()->addSeconds(max(1, $leaseSeconds));
            $claimed = new Collection;

            foreach ($events as $event) {
                $updated = OutboxEvent::query()
                    ->whereKey($event->getKey())
                    ->whereIn('status', [OutboxStatus::Pending->value, OutboxStatus::Available->value, OutboxStatus::Retryable->value])
                    ->where('available_at', '<=', $now)
                    ->update([
                        'status' => OutboxStatus::Publishing->value,
                        'locked_by' => $worker,
                        'locked_at' => $now,
                        'lease_until' => $leaseUntil,
                        'last_attempt_at' => $now,
                        'attempts' => $event->attempts + 1,
                    ]);

                if ($updated === 1) {
                    $claimed->push(OutboxEvent::query()->findOrFail($event->getKey()));
                }
            }

            return $claimed;
        }, 5);
    }

    public function markPublished(OutboxEvent|int|string $event, string $worker): OutboxEvent
    {
        $this->assertWorker($worker);
        $id = $this->id($event);
        $updated = OutboxEvent::query()
            ->whereKey($id)
            ->where('status', OutboxStatus::Publishing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => OutboxStatus::Published->value,
                'published_at' => now(),
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
                'last_error' => null,
            ]);

        return $this->transitionResult($id, $updated, 'publish');
    }

    public function markRetryable(OutboxEvent|int|string $event, string $worker, ?string $error = null, int $maxAttempts = 5): OutboxEvent
    {
        $this->assertWorker($worker);
        $model = $this->resolve($event);

        if ($model->attempts >= $maxAttempts) {
            return $this->markDead($model, $worker, $error);
        }

        $updated = OutboxEvent::query()
            ->whereKey($model->getKey())
            ->where('status', OutboxStatus::Publishing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => OutboxStatus::Retryable->value,
                'available_at' => now()->addSeconds($this->backoffSeconds($model->attempts)),
                'locked_by' => null,
                'locked_at' => null,
                'lease_until' => null,
                'last_error' => $error === null ? null : mb_substr($error, 0, 2000),
            ]);

        return $this->transitionResult($model->getKey(), $updated, 'retry');
    }

    public function markDead(OutboxEvent|int|string $event, string $worker, ?string $error = null): OutboxEvent
    {
        $this->assertWorker($worker);
        $id = $this->id($event);
        $updated = OutboxEvent::query()
            ->whereKey($id)
            ->where('status', OutboxStatus::Publishing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->update([
                'status' => OutboxStatus::Dead->value,
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
            $query = OutboxEvent::query()
                ->where('status', OutboxStatus::Publishing->value)
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
                $updated = OutboxEvent::query()
                    ->whereKey($event->getKey())
                    ->where('status', OutboxStatus::Publishing->value)
                    ->where('locked_by', $event->locked_by)
                    ->where('lease_until', '<=', $now)
                    ->update($terminal ? [
                        'status' => OutboxStatus::Dead->value,
                        'dead_at' => $now,
                        'locked_by' => null,
                        'locked_at' => null,
                        'lease_until' => null,
                        'last_error' => 'Outbox lease expired.',
                    ] : [
                        'status' => OutboxStatus::Retryable->value,
                        'available_at' => $now->copy()->addSeconds($this->backoffSeconds($event->attempts)),
                        'locked_by' => null,
                        'locked_at' => null,
                        'lease_until' => null,
                        'last_error' => 'Outbox lease expired.',
                    ]);
                $count += $updated;
            }

            return $count;
        }, 5);
    }

    public function isLeaseValid(OutboxEvent|int|string $event, string $worker): bool
    {
        $this->assertWorker($worker);

        return OutboxEvent::query()
            ->whereKey($this->id($event))
            ->where('status', OutboxStatus::Publishing->value)
            ->where('locked_by', $worker)
            ->where('lease_until', '>', now())
            ->exists();
    }

    private function resolve(OutboxEvent|int|string $event): OutboxEvent
    {
        if ($event instanceof OutboxEvent) {
            return $event->fresh() ?? throw (new ModelNotFoundException)->setModel(OutboxEvent::class, [$event->getKey()]);
        }

        return OutboxEvent::query()->findOrFail($event);
    }

    private function id(OutboxEvent|int|string $event): int|string
    {
        return $event instanceof OutboxEvent ? $event->getKey() : $event;
    }

    private function transitionResult(int|string $id, int $updated, string $transition): OutboxEvent
    {
        if ($updated !== 1) {
            throw new \LogicException("Unable to {$transition} outbox event: lease or status is no longer valid.");
        }

        return OutboxEvent::query()->findOrFail($id);
    }

    private function assertWorker(string $worker): void
    {
        if (trim($worker) === '') {
            throw new \InvalidArgumentException('A non-empty outbox worker token is required.');
        }
    }

    private function backoffSeconds(int $attempts): int
    {
        return min(3600, 2 ** min(10, max(0, $attempts - 1)));
    }
}
