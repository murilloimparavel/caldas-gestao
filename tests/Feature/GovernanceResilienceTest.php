<?php

use App\Actions\Entitlements\GrantEntitlement;
use App\Actions\Entitlements\UpdateEntitlement;
use App\Actions\Identity\OnboardTenant;
use App\Enums\EntitlementStatus;
use App\Enums\InboxStatus;
use App\Enums\OutboxStatus;
use App\Jobs\DispatchOutboxEvent;
use App\Models\AuditEvent;
use App\Models\Entitlement;
use App\Models\InboxEvent;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\EntitlementService;
use App\Support\IdempotencyService;
use App\Support\IdentityEventRecorder;
use App\Support\InboxEventStore;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

it('evaluates entitlement vigency with an inclusive start and exclusive end', function () {
    $tenant = Tenant::factory()->create();
    $startsAt = Carbon::parse('2026-08-25 12:00:00', 'UTC');
    $endsAt = $startsAt->copy()->addHour();
    $entitlement = Entitlement::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'goals',
        'status' => EntitlementStatus::Active,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    $service = app(EntitlementService::class);

    expect($service->can($tenant, 'goals', $startsAt))->toBeTrue()
        ->and($service->can($tenant, 'goals', $endsAt))->toBeFalse()
        ->and($entitlement->isActiveAt($startsAt))->toBeTrue();
});

it('executes an idempotent command once and replays its sanitized result', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->create();
    $service = app(IdempotencyService::class);
    $calls = 0;

    $first = $service->execute($tenant, $actor, 'command-1', ['name' => 'one'], function () use (&$calls): array {
        $calls++;

        return ['value' => ['resource_id' => (string) Str::uuid7()], 'response_code' => 201];
    });
    $second = $service->execute($tenant, $actor, 'command-1', ['name' => 'one'], function () use (&$calls): array {
        $calls++;

        return ['value' => ['resource_id' => 'wrong'], 'response_code' => 201];
    });

    expect($calls)->toBe(1)
        ->and($first->replayed)->toBeFalse()
        ->and($second->replayed)->toBeTrue()
        ->and($second->responseCode)->toBe(201)
        ->and($second->value)->toBe($first->value['value']);

    expect(fn () => $service->execute($tenant, $actor, 'command-1', ['name' => 'different'], fn (): array => ['value' => []]))
        ->toThrow(ConflictHttpException::class);
});

it('rolls back partial idempotent effects and records a failed attempt', function () {
    $tenant = Tenant::factory()->create();
    $service = app(IdempotencyService::class);

    expect(fn () => $service->execute($tenant, null, 'command-fails', ['b' => 2, 'a' => 1], function (): never {
        Tenant::factory()->create();
        throw new RuntimeException('expected failure');
    }))->toThrow(RuntimeException::class, 'expected failure');

    expect(Tenant::query()->count())->toBe(1)
        ->and($service->execute($tenant, null, 'command-fails', ['a' => 1, 'b' => 2], fn (): array => ['value' => ['status' => 'unexpected']])->replayed)->toBeTrue();
});

it('writes audit events with a minimal metadata envelope', function () {
    $tenant = Tenant::factory()->create();
    $actor = User::factory()->create();
    $event = app(AuditEventWriter::class)->record([
        'tenant_id' => $tenant->getKey(),
        'actor_user_id' => $actor->getKey(),
        'action' => 'test.created',
        'resource_type' => 'test',
        'reason' => "safe\nreason\x00".str_repeat('x', 600),
        'metadata' => ['status' => 'active'],
    ]);

    expect($event)->toBeInstanceOf(AuditEvent::class)
        ->and($event->metadata)->toBe(['status' => 'active'])
        ->and($event->reason)->not->toContain("\n")
        ->and(strlen((string) $event->reason))->toBeLessThanOrEqual(500)
        ->and(AuditEvent::query()->whereKey($event->getKey())->exists())->toBeTrue();

    expect(fn () => $event->delete())->toThrow(LogicException::class);
});

it('claims outbox events and recovers an expired lease', function () {
    $tenant = Tenant::factory()->create();
    $event = app(OutboxEventStore::class)->enqueue([
        'tenant_id' => $tenant->getKey(),
        'aggregate_type' => 'test',
        'aggregate_id' => (string) Str::uuid7(),
        'event_type' => 'test.created',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ]);
    $claimed = app(OutboxEventStore::class)->claim('worker-a', 1, 60)->first();

    expect($claimed)->toBeInstanceOf(OutboxEvent::class)
        ->and($claimed?->status)->toBe(OutboxStatus::Publishing)
        ->and($claimed?->locked_by)->toBe('worker-a');

    $event->forceFill(['lease_until' => now()->subSecond()])->save();
    expect(app(OutboxEventStore::class)->reapExpiredLeases('reaper-a'))->toBe(1)
        ->and($event->fresh()->status)->toBe(OutboxStatus::Retryable);

    expect(fn () => app(OutboxEventStore::class)->markPublished($event, 'worker-a'))
        ->toThrow(LogicException::class, 'lease');
});

it('deduplicates inbox delivery and processes a claimed event', function () {
    $tenant = Tenant::factory()->create();
    $store = app(InboxEventStore::class);
    $attributes = [
        'tenant_id' => $tenant->getKey(),
        'consumer' => 'test.consumer',
        'event_id' => (string) Str::uuid7(),
        'event_type' => 'test.created',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ];
    $first = $store->receive($attributes);
    $second = $store->receive($attributes);
    $claimed = $store->claim('test.consumer', 'worker-a')->first();
    $processed = $store->markProcessed($claimed ?? $second, 'worker-a');

    expect($first->getKey())->toBe($second->getKey())
        ->and($processed->status)->toBe(InboxStatus::Processed)
        ->and(InboxEvent::query()->where('consumer', 'test.consumer')->count())->toBe(1);
});

it('rejects a conflicting duplicate inbox envelope', function () {
    $tenant = Tenant::factory()->create();
    $store = app(InboxEventStore::class);
    $attributes = [
        'tenant_id' => $tenant->getKey(),
        'consumer' => 'test.consumer',
        'event_id' => (string) Str::uuid7(),
        'event_type' => 'test.created',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ];
    $store->receive($attributes);

    expect(fn () => $store->receive([...$attributes, 'event_type' => 'test.changed']))
        ->toThrow(LogicException::class, 'conflicts');
});

it('does not allow terminal entitlements to be edited or unsafe config to be granted', function () {
    $actor = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($actor, ['name' => 'Governance test']);
    $context = TenantContext::forUser($actor, $tenant->getKey());
    $expired = Entitlement::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => EntitlementStatus::Expired,
    ]);
    $revoked = Entitlement::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'revoked-feature',
        'status' => EntitlementStatus::Revoked,
    ]);

    expect(fn () => (new UpdateEntitlement)->handle($actor, $context, $expired, ['quantity' => 2]))
        ->toThrow(LogicException::class, 'immutable')
        ->and(fn () => (new UpdateEntitlement)->handle($actor, $context, $revoked, ['quantity' => 2]))
        ->toThrow(LogicException::class, 'immutable')
        ->and(fn () => (new GrantEntitlement)->handle($actor, $context, [
            'key' => 'unsafe',
            'config' => ['password' => 'secret'],
        ]))->toThrow(InvalidArgumentException::class);
});

it('delivers an outbox event to the local inbox only with its valid worker lease', function () {
    $tenant = Tenant::factory()->create();
    $event = app(OutboxEventStore::class)->enqueue([
        'tenant_id' => $tenant->getKey(),
        'aggregate_type' => 'test',
        'aggregate_id' => (string) Str::uuid7(),
        'event_type' => 'test.created',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ]);
    $worker = 'pump-worker';
    app(OutboxEventStore::class)->claim($worker, 1, 60);

    app(DispatchOutboxEvent::class, [
        'eventId' => (string) $event->event_id,
        'consumer' => 'local.inbox',
        'workerToken' => $worker,
    ])->handle(app(OutboxEventStore::class), app(InboxEventStore::class));

    expect($event->fresh()->status)->toBe(OutboxStatus::Published)
        ->and(InboxEvent::query()->where('event_id', $event->event_id)->where('consumer', 'local.inbox')->count())->toBe(1);
});

it('dispatches outbox jobs only after the surrounding transaction commits', function () {
    config(['queue.default' => 'database']);
    $tenant = Tenant::factory()->create();
    $event = app(OutboxEventStore::class)->enqueue([
        'tenant_id' => $tenant->getKey(),
        'aggregate_type' => 'test',
        'aggregate_id' => (string) Str::uuid7(),
        'event_type' => 'test.created',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ]);
    $worker = 'pump-worker';
    app(OutboxEventStore::class)->claim($worker, 1, 60);

    try {
        DB::transaction(function () use ($event, $worker): void {
            DispatchOutboxEvent::dispatch((string) $event->event_id, 'local.inbox', $worker);
            throw new RuntimeException('rollback dispatch');
        });
    } catch (RuntimeException) {
    }
    expect(DB::table('jobs')->count())->toBe(0);

    DB::transaction(function () use ($event, $worker): void {
        DispatchOutboxEvent::dispatch((string) $event->event_id, 'local.inbox', $worker);
    });
    expect(DB::table('jobs')->count())->toBe(1);
});

it('pump command claims and dispatches a real outbox job', function () {
    config(['queue.default' => 'database']);
    $tenant = Tenant::factory()->create();
    $event = app(OutboxEventStore::class)->enqueue([
        'tenant_id' => $tenant->getKey(),
        'aggregate_type' => 'test',
        'aggregate_id' => (string) Str::uuid7(),
        'event_type' => 'test.pumped',
        'payload' => ['resource_id' => (string) Str::uuid7()],
    ]);

    expect(Artisan::call('outbox:pump', ['--limit' => 1]))->toBe(0)
        ->and($event->fresh()->status)->toBe(OutboxStatus::Publishing)
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('does not leave governance events when their transaction rolls back', function () {
    $tenant = Tenant::factory()->create();
    $eventId = (string) Str::uuid7();

    try {
        DB::transaction(function () use ($tenant, $eventId): void {
            app(AuditEventWriter::class)->record([
                'event_id' => $eventId,
                'tenant_id' => $tenant->getKey(),
                'action' => 'test.rolled_back',
                'resource_type' => 'test',
            ]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // The assertion below proves both writes share the transaction boundary.
    }

    expect(AuditEvent::query()->where('event_id', $eventId)->exists())->toBeFalse();
});

it('rolls back identity state, audit and outbox as one transaction', function () {
    $actor = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($actor, ['name' => 'Atomic governance']);
    $context = TenantContext::forUser($actor, $tenant->getKey());
    $resource = Entitlement::factory()->create(['tenant_id' => $tenant->getKey()]);
    $eventId = (string) Str::uuid7();

    try {
        DB::transaction(function () use ($actor, $context, $resource, $eventId): void {
            app(IdentityEventRecorder::class)->recordForTenant($actor, $context->tenant, 'test.atomic', $resource, ['resource_id' => $resource->getKey()]);
            throw new RuntimeException($eventId);
        });
    } catch (RuntimeException) {
    }

    expect(AuditEvent::query()->where('action', 'test.atomic')->exists())->toBeFalse()
        ->and(OutboxEvent::query()->where('event_type', 'test.atomic')->exists())->toBeFalse();
});
