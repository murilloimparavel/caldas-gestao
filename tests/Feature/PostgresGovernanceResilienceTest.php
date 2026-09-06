<?php

use App\Enums\InboxStatus;
use App\Enums\OutboxStatus;
use App\Models\AuditEvent;
use App\Models\Entitlement;
use App\Models\InboxEvent;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use App\Support\AuditEventWriter;
use App\Support\IdempotencyService;
use App\Support\InboxEventStore;
use App\Support\OutboxEventStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

function requirePostgresGovernanceGate(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL governance guards are enforced by the PostgreSQL CI job.');
    }
}

function governancePostgresPdo(string $connection = 'pgsql'): PDO
{
    /** @var array{host:string,port:int|string,database:string,username:string,password:string} $config */
    $config = config("database.connections.{$connection}");

    return new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

/** @return array{id:string,slug:string} */
function seedGovernanceTenant(PDO $connection): array
{
    $id = (string) Str::uuid7();
    $slug = 'governance-'.Str::lower(Str::random(18));
    $connection->prepare(
        "insert into app.tenants (id, slug, name, status, timezone, default_currency, lock_version, created_at, updated_at) values (?, ?, 'Governance tenant', 'active', 'UTC', 'BRL', 0, now(), now())",
    )->execute([$id, $slug]);

    return ['id' => $id, 'slug' => $slug];
}

function seedGovernanceUser(PDO $connection): string
{
    $id = (string) Str::uuid7();
    $email = "governance-{$id}@example.test";
    $connection->prepare(
        "insert into app.users (id, name, email, email_normalized, password, created_at, updated_at) values (?, 'Governance user', ?, ?, 'password', now(), now())",
    )->execute([$id, $email, $email]);

    return $id;
}

function seedGovernanceOutbox(PDO $connection, string $tenantId): string
{
    $eventId = (string) Str::uuid7();
    $connection->prepare("insert into app.outbox_events (event_id, tenant_id, aggregate_type, aggregate_id, aggregate_version, event_type, event_version, payload, status, occurred_at, available_at, attempts, created_at) values (?, ?, 'test', ?, 1, 'test.claimed', 1, '{}'::jsonb, 'pending', now(), now(), 0, now())")->execute([$eventId, $tenantId, (string) Str::uuid7()]);

    return $eventId;
}

function seedGovernanceInbox(PDO $connection, string $tenantId, string $consumer): string
{
    $eventId = (string) Str::uuid7();
    $connection->prepare("insert into app.inbox_events (tenant_id, consumer, event_id, event_type, event_version, payload, status, attempts, received_at, available_at, created_at, updated_at) values (?, ?, ?, 'test.claimed', 1, '{}'::jsonb, 'received', 0, now(), now(), now(), now())")->execute([$tenantId, $consumer, $eventId]);

    return $eventId;
}

function governancePrivilege(PDO $connection, string $table, string $privilege): bool
{
    $statement = $connection->prepare('select case when has_table_privilege(current_user, ?, ?) then 1 else 0 end');
    $statement->execute([$table, $privilege]);

    return (int) $statement->fetchColumn() === 1;
}

/** @return array{0:resource,1:array<int,resource>} */
function startGovernanceIdempotencyProcess(string $tenantId, ?string $actorId, string $key): array
{
    $tenant = var_export($tenantId, true);
    $actor = var_export($actorId, true);
    $idempotencyKey = var_export($key, true);
    $script = strtr(<<<'PHP'
        $result = app(\App\Support\IdempotencyService::class)->execute(
            __TENANT__,
            __ACTOR__,
            __KEY__,
            ['request' => 'same'],
            function (): array {
                usleep(700000);

                return ['value' => ['status' => 'created']];
            },
        );
        echo 'IDEMPOTENCY='.($result->replayed ? 'replay' : 'new');
        PHP, [
        '__TENANT__' => $tenant,
        '__ACTOR__' => $actor,
        '__KEY__' => $idempotencyKey,
    ]);
    $process = proc_open(
        [PHP_BINARY, base_path('artisan'), 'tinker', '--execute', $script],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the idempotency process.');
    }

    return [$process, $pipes];
}

/** @param array{0:resource,1:array<int,resource>} $process */
function finishGovernanceProcess(array $process): array
{
    [$resource, $pipes] = $process;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit_code' => proc_close($resource), 'stdout' => $stdout, 'stderr' => $stderr];
}

/** @return array{0:resource,1:array<int,resource>} */
function startGovernanceStoreClaimProcess(string $store, string $worker, ?string $consumer = null): array
{
    $workerToken = var_export($worker, true);
    $consumerName = var_export($consumer, true);
    $script = match ($store) {
        'outbox' => <<<'PHP'
            $events = app(\App\Support\OutboxEventStore::class)->claim(__WORKER__, 2, 60);
            echo 'CLAIM='.base64_encode(json_encode($events->pluck('event_id')->values()->all(), JSON_THROW_ON_ERROR));
            PHP,
        'inbox' => <<<'PHP'
            $events = app(\App\Support\InboxEventStore::class)->claim(__CONSUMER__, __WORKER__, 2, 60);
            echo 'CLAIM='.base64_encode(json_encode($events->pluck('event_id')->values()->all(), JSON_THROW_ON_ERROR));
            PHP,
        default => throw new InvalidArgumentException("Unsupported store [{$store}]."),
    };
    $process = proc_open(
        [PHP_BINARY, base_path('artisan'), 'tinker', '--execute', strtr($script, [
            '__CONSUMER__' => $consumerName,
            '__WORKER__' => $workerToken,
        ])],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the store claim process.');
    }

    return [$process, $pipes];
}

/** @return list<string> */
function claimedGovernanceEvents(string $stdout): array
{
    preg_match('/CLAIM=([A-Za-z0-9+\/=]+)/', $stdout, $match);
    $payload = isset($match[1]) ? base64_decode($match[1], true) : false;
    $events = is_string($payload) ? json_decode($payload, true, flags: JSON_THROW_ON_ERROR) : null;

    if (! is_array($events) || ! array_is_list($events) || ! collect($events)->every(static fn (mixed $event): bool => is_string($event))) {
        throw new RuntimeException('The store claim process did not emit a valid event list.');
    }

    return $events;
}

test('enforces append-only audit and system role guards for runtime and migration roles', function () {
    requirePostgresGovernanceGate();

    $runtime = governancePostgresPdo();
    $migration = governancePostgresPdo('migration');
    $tenant = seedGovernanceTenant($runtime);
    $auditId = (string) Str::uuid7();
    $roleId = (string) Str::uuid7();
    $runtime->prepare("insert into app.audit_events (event_id, tenant_id, action, resource_type, metadata, occurred_at) values (?, ?, 'created', 'test', '{}'::jsonb, now())")->execute([$auditId, $tenant['id']]);
    $runtime->prepare("insert into app.roles (id, tenant_id, key, name, is_system, lock_version, created_at, updated_at) values (?, ?, 'owner', 'Owner', true, 0, now(), now())")->execute([$roleId, $tenant['id']]);

    expect(governancePrivilege($runtime, 'app.audit_events', 'INSERT'))->toBeTrue()
        ->and(governancePrivilege($runtime, 'app.audit_events', 'UPDATE'))->toBeFalse()
        ->and(governancePrivilege($runtime, 'app.audit_events', 'DELETE'))->toBeFalse()
        ->and(governancePrivilege($runtime, 'app.audit_events', 'TRUNCATE'))->toBeFalse()
        ->and(governancePrivilege($runtime, 'app.audit_events', 'TRIGGER'))->toBeFalse()
        ->and(governancePrivilege($runtime, 'app.roles', 'TRUNCATE'))->toBeFalse()
        ->and(governancePrivilege($runtime, 'app.roles', 'TRIGGER'))->toBeFalse()
        ->and(fn () => $runtime->prepare("update app.audit_events set action = 'tampered' where event_id = ?")->execute([$auditId]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare('delete from app.audit_events where event_id = ?')->execute([$auditId]))->toThrow(PDOException::class)
        ->and(fn () => $migration->prepare("update app.audit_events set action = 'tampered' where event_id = ?")->execute([$auditId]))->toThrow(PDOException::class)
        ->and(fn () => $migration->prepare('delete from app.audit_events where event_id = ?')->execute([$auditId]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("update app.roles set name = 'Changed', lock_version = 1 where id = ?")->execute([$roleId]))->toThrow(PDOException::class)
        ->and(fn () => $migration->prepare('delete from app.roles where id = ?')->execute([$roleId]))->toThrow(PDOException::class);
});

test('enforces governance checks, NULLS NOT DISTINCT and cross-tenant scope', function () {
    requirePostgresGovernanceGate();

    $runtime = governancePostgresPdo();
    $tenant = seedGovernanceTenant($runtime);
    $otherTenant = seedGovernanceTenant($runtime);
    $actorOne = seedGovernanceUser($runtime);
    $actorTwo = seedGovernanceUser($runtime);
    $unitId = (string) Str::uuid7();
    $runtime->prepare("insert into app.units (id, tenant_id, slug, name, status, lock_version, created_at, updated_at) values (?, ?, 'other-unit', 'Other unit', 'active', 0, now(), now())")->execute([$unitId, $otherTenant['id']]);

    $idempotency = $runtime->prepare("insert into app.idempotency_keys (tenant_id, actor_user_id, key, request_hash, status, expires_at, created_at, updated_at) values (?, ?, ?, repeat('a', 64), 'started', now() + interval '1 hour', now(), now())");
    $idempotency->execute([$tenant['id'], null, 'null-actor']);
    $idempotency->execute([$tenant['id'], $actorOne, 'actor']);
    $idempotency->execute([$tenant['id'], $actorTwo, 'actor']);

    expect(fn () => $idempotency->execute([$tenant['id'], null, 'null-actor']))->toThrow(PDOException::class)
        ->and(fn () => $idempotency->execute([$tenant['id'], $actorOne, 'actor']))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("insert into app.entitlements (id, tenant_id, key, status, starts_at, source, config, lock_version, created_at, updated_at) values (?, ?, 'invalid', 'invalid', now(), 'manual', '{}'::jsonb, 0, now(), now())")->execute([(string) Str::uuid7(), $tenant['id']]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("insert into app.entitlements (id, tenant_id, key, status, starts_at, ends_at, source, config, lock_version, created_at, updated_at) values (?, ?, 'dates', 'active', now(), now(), 'manual', '{}'::jsonb, 0, now(), now())")->execute([(string) Str::uuid7(), $tenant['id']]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("insert into app.outbox_events (event_id, tenant_id, aggregate_type, aggregate_id, aggregate_version, event_type, event_version, payload, status, occurred_at, available_at, attempts, created_at) values (?, ?, 'test', ?, 1, 'test', 1, '{}'::jsonb, 'publishing', now(), now(), 0, now())")->execute([(string) Str::uuid7(), $tenant['id'], (string) Str::uuid7()]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("insert into app.inbox_events (tenant_id, consumer, event_id, event_type, event_version, payload, status, attempts, received_at, available_at, created_at, updated_at) values (?, 'checks', ?, 'test', 1, '{}'::jsonb, 'processed', 0, now(), now(), now(), now())")->execute([$tenant['id'], (string) Str::uuid7()]))->toThrow(PDOException::class)
        ->and(fn () => $runtime->prepare("insert into app.audit_events (event_id, tenant_id, unit_id, action, resource_type, metadata, occurred_at) values (?, ?, ?, 'cross.tenant', 'test', '{}'::jsonb, now())")->execute([(string) Str::uuid7(), $tenant['id'], $unitId]))->toThrow(PDOException::class);

    $runtime->prepare("insert into app.entitlements (id, tenant_id, key, status, starts_at, source, config, lock_version, created_at, updated_at) values (?, ?, 'terminal', 'revoked', now(), 'manual', '{}'::jsonb, 0, now(), now())")->execute([(string) Str::uuid7(), $tenant['id']]);
});

test('uses SKIP LOCKED for concurrent outbox and inbox claims', function () {
    requirePostgresGovernanceGate();

    $first = governancePostgresPdo();
    $second = governancePostgresPdo();
    $tenant = seedGovernanceTenant($first);
    $outboxId = (string) Str::uuid7();
    $inboxId = (string) Str::uuid7();
    $first->prepare("insert into app.outbox_events (event_id, tenant_id, aggregate_type, aggregate_id, aggregate_version, event_type, event_version, payload, status, occurred_at, available_at, attempts, created_at) values (?, ?, 'test', ?, 1, 'test', 1, '{}'::jsonb, 'pending', now(), now() + interval '1 hour', 0, now())")->execute([$outboxId, $tenant['id'], (string) Str::uuid7()]);
    $first->prepare("insert into app.inbox_events (tenant_id, consumer, event_id, event_type, event_version, payload, status, attempts, received_at, available_at, created_at, updated_at) values (?, 'concurrent', ?, 'test', 1, '{}'::jsonb, 'received', 0, now(), now() + interval '1 hour', now(), now())")->execute([$tenant['id'], $inboxId]);

    $first->beginTransaction();
    $first->prepare('select id from app.outbox_events where event_id = ? for update skip locked')->execute([$outboxId]);
    $first->prepare('select id from app.inbox_events where event_id = ? for update skip locked')->execute([$inboxId]);
    $outboxClaim = $second->prepare('select id from app.outbox_events where event_id = ? for update skip locked');
    $inboxClaim = $second->prepare('select id from app.inbox_events where event_id = ? for update skip locked');
    $outboxClaim->execute([$outboxId]);
    $inboxClaim->execute([$inboxId]);

    expect($outboxClaim->fetchColumn())->toBeFalse()
        ->and($inboxClaim->fetchColumn())->toBeFalse();

    $first->rollBack();
});

test('serializes idempotency keys across independent PostgreSQL processes for null and named actors', function () {
    requirePostgresGovernanceGate();

    $runtime = governancePostgresPdo();
    $tenant = seedGovernanceTenant($runtime);
    $actor = seedGovernanceUser($runtime);

    foreach ([null, $actor] as $index => $actorId) {
        $key = 'concurrent-'.$index;
        $first = startGovernanceIdempotencyProcess($tenant['id'], $actorId, $key);
        usleep(150_000);
        $second = startGovernanceIdempotencyProcess($tenant['id'], $actorId, $key);
        $firstResult = finishGovernanceProcess($first);
        $secondResult = finishGovernanceProcess($second);

        expect($firstResult['exit_code'])->toBe(0, $firstResult['stderr'])
            ->and($secondResult['exit_code'])->toBe(0, $secondResult['stderr'])
            ->and($firstResult['stdout'])->toContain('IDEMPOTENCY=new')
            ->and($secondResult['stdout'])->toContain('IDEMPOTENCY=replay');
    }

    $service = app(IdempotencyService::class);
    $count = $runtime->prepare('select count(*) from app.idempotency_keys where tenant_id = ?');
    $count->execute([$tenant['id']]);

    expect(fn () => $service->execute($tenant['id'], null, 'concurrent-0', ['request' => 'different'], fn (): array => ['value' => []]))->toThrow(ConflictHttpException::class)
        ->and((int) $count->fetchColumn())->toBe(2);
});

test('claims disjoint outbox and inbox batches through real stores in independent PostgreSQL processes', function () {
    requirePostgresGovernanceGate();

    $runtime = governancePostgresPdo();
    $tenant = seedGovernanceTenant($runtime);
    $consumer = 'concurrent-store-'.Str::lower(Str::random(12));
    $runtime->exec('delete from app.outbox_events');
    $runtime->exec('delete from app.inbox_events');
    $outboxIds = array_map(static fn (int $_): string => seedGovernanceOutbox($runtime, $tenant['id']), range(1, 4));
    $inboxIds = array_map(static fn (int $_): string => seedGovernanceInbox($runtime, $tenant['id'], $consumer), range(1, 4));

    $outboxFirst = startGovernanceStoreClaimProcess('outbox', 'outbox-worker-a');
    $outboxSecond = startGovernanceStoreClaimProcess('outbox', 'outbox-worker-b');
    $inboxFirst = startGovernanceStoreClaimProcess('inbox', 'inbox-worker-a', $consumer);
    $inboxSecond = startGovernanceStoreClaimProcess('inbox', 'inbox-worker-b', $consumer);
    $outboxResults = [finishGovernanceProcess($outboxFirst), finishGovernanceProcess($outboxSecond)];
    $inboxResults = [finishGovernanceProcess($inboxFirst), finishGovernanceProcess($inboxSecond)];
    $claimedOutbox = array_merge(...array_map(static fn (array $result): array => claimedGovernanceEvents($result['stdout']), $outboxResults));
    $claimedInbox = array_merge(...array_map(static fn (array $result): array => claimedGovernanceEvents($result['stdout']), $inboxResults));
    sort($outboxIds);
    sort($inboxIds);
    sort($claimedOutbox);
    sort($claimedInbox);

    expect(collect($outboxResults)->every(static fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and(collect($inboxResults)->every(static fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and($claimedOutbox)->toBe($outboxIds)
        ->and($claimedInbox)->toBe($inboxIds)
        ->and(array_unique($claimedOutbox))->toHaveCount(4)
        ->and(array_unique($claimedInbox))->toHaveCount(4)
        ->and(OutboxEvent::query()->whereIn('event_id', $outboxIds)->where('status', OutboxStatus::Publishing)->count())->toBe(4)
        ->and(InboxEvent::query()->whereIn('event_id', $inboxIds)->where('status', InboxStatus::Processing)->count())->toBe(4);
});

test('prevents stale workers from finalizing after leases are reaped', function () {
    requirePostgresGovernanceGate();

    $runtime = governancePostgresPdo();
    $tenant = seedGovernanceTenant($runtime);
    $outboxId = (string) Str::uuid7();
    $inboxId = (string) Str::uuid7();
    $runtime->prepare("insert into app.outbox_events (event_id, tenant_id, aggregate_type, aggregate_id, aggregate_version, event_type, event_version, payload, status, occurred_at, available_at, locked_by, locked_at, lease_until, attempts, created_at) values (?, ?, 'test', ?, 1, 'test', 1, '{}'::jsonb, 'publishing', now(), now(), 'stale', now() - interval '2 minutes', now() - interval '1 minute', 1, now())")->execute([$outboxId, $tenant['id'], (string) Str::uuid7()]);
    $runtime->prepare("insert into app.inbox_events (tenant_id, consumer, event_id, event_type, event_version, payload, status, attempts, received_at, available_at, locked_by, locked_at, lease_until, created_at, updated_at) values (?, 'stale', ?, 'test', 1, '{}'::jsonb, 'processing', 1, now(), now(), 'stale', now() - interval '2 minutes', now() - interval '1 minute', now(), now())")->execute([$tenant['id'], $inboxId]);

    $outbox = app(OutboxEventStore::class);
    $inbox = app(InboxEventStore::class);
    $outboxEvent = OutboxEvent::query()->where('event_id', $outboxId)->firstOrFail();
    $inboxEvent = InboxEvent::query()->where('event_id', $inboxId)->firstOrFail();

    expect($outbox->reapExpiredLeases('reaper'))->toBe(1)
        ->and($inbox->reapExpiredLeases('reaper'))->toBe(1)
        ->and(fn () => $outbox->markPublished($outboxEvent, 'stale'))->toThrow(LogicException::class, 'lease')
        ->and(fn () => $inbox->markProcessed($inboxEvent, 'stale'))->toThrow(LogicException::class, 'lease')
        ->and($outboxEvent->fresh()->status)->toBe(OutboxStatus::Retryable)
        ->and($inboxEvent->fresh()->status)->toBe(InboxStatus::Retryable);
});

test('keeps aggregate, audit and outbox writes atomic and idempotency failures durable', function () {
    requirePostgresGovernanceGate();

    $tenant = Tenant::factory()->create();
    $entitlementId = (string) Str::uuid7();
    $eventId = (string) Str::uuid7();
    try {
        DB::transaction(function () use ($tenant, $entitlementId, $eventId): void {
            Entitlement::query()->create(['id' => $entitlementId, 'tenant_id' => $tenant->getKey(), 'key' => 'atomic', 'status' => 'active', 'starts_at' => now(), 'source' => 'manual']);
            app(AuditEventWriter::class)->record(['event_id' => $eventId, 'tenant_id' => $tenant->getKey(), 'action' => 'atomic', 'resource_type' => 'entitlement', 'resource_id' => $entitlementId]);
            app(OutboxEventStore::class)->enqueue(['event_id' => $eventId, 'tenant_id' => $tenant->getKey(), 'aggregate_type' => 'entitlement', 'aggregate_id' => $entitlementId, 'event_type' => 'atomic']);
            throw new RuntimeException('Rollback.');
        });
    } catch (RuntimeException) {
    }

    $service = app(IdempotencyService::class);
    expect(Entitlement::query()->whereKey($entitlementId)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('event_id', $eventId)->exists())->toBeFalse()
        ->and(OutboxEvent::query()->where('event_id', $eventId)->exists())->toBeFalse()
        ->and(fn () => $service->execute($tenant, null, 'postgres-failure', ['a' => 1], function (): never {
            Tenant::factory()->create();
            throw new RuntimeException('Expected rollback.');
        }))->toThrow(RuntimeException::class, 'Expected rollback.')
        ->and($service->execute($tenant, null, 'postgres-failure', ['a' => 1], fn (): array => ['value' => []])->replayed)->toBeTrue()
        ->and(fn () => $service->execute($tenant, null, 'postgres-failure', ['a' => 2], fn (): array => ['value' => []]))->toThrow(ConflictHttpException::class);
});
