<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function requirePostgresBootstrapGate(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL bootstrap concurrency is enforced by the PostgreSQL CI job.');
    }
}

function postgresBootstrapPdo(string $connection = 'pgsql'): PDO
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

/** @return array<string, string> */
function postgresBootstrapProcessEnvironment(): array
{
    /** @var array{host:string,port:int|string,database:string,username:string,password:string,sslmode:string} $runtime */
    $runtime = config('database.connections.pgsql');
    /** @var array{host:string,port:int|string,database:string,username:string,password:string,sslmode:string} $migration */
    $migration = config('database.connections.migration');
    $inherited = getenv();

    return array_map(
        static fn (mixed $value): string => (string) $value,
        array_filter(array_merge(is_array($inherited) ? $inherited : [], [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $runtime['host'],
            'DB_PORT' => $runtime['port'],
            'DB_DATABASE' => $runtime['database'],
            'DB_USERNAME' => $runtime['username'],
            'DB_PASSWORD' => $runtime['password'],
            'DB_SCHEMA' => config('database.schema'),
            'DB_SEARCH_PATH' => config('database.search_path'),
            'DB_SSLMODE' => $runtime['sslmode'],
            'DB_RUNTIME_ROLE' => config('database.runtime_role'),
            'DB_PROVISION_STRICT' => 'true',
            'MIGRATION_DB_CONNECTION' => 'pgsql',
            'MIGRATION_DB_HOST' => $migration['host'],
            'MIGRATION_DB_PORT' => $migration['port'],
            'MIGRATION_DB_DATABASE' => $migration['database'],
            'MIGRATION_DB_USERNAME' => $migration['username'],
            'MIGRATION_DB_PASSWORD' => $migration['password'],
            'MIGRATION_DB_SSLMODE' => $migration['sslmode'],
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'BOOTSTRAP_TENANT_PASSWORD' => 'Secret123!',
        ]), static fn (mixed $value): bool => $value !== null),
    );
}

/** @return array{0:resource,1:array<int, resource>} */
function startPostgresBootstrapProcess(array $options): array
{
    $arguments = [PHP_BINARY, base_path('artisan'), 'app:bootstrap-tenant'];

    foreach ($options as $name => $value) {
        $option = '--'.ltrim($name, '-');
        $arguments[] = $value === true ? $option : "{$option}={$value}";
    }

    $arguments[] = '--no-interaction';
    $process = proc_open(
        $arguments,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
        postgresBootstrapProcessEnvironment(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated bootstrap process.');
    }

    return [$process, $pipes];
}

/** @param array{0:resource,1:array<int, resource>} $process */
function finishPostgresBootstrapProcess(array $process): array
{
    [$resource, $pipes] = $process;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit_code' => proc_close($resource), 'stdout' => $stdout, 'stderr' => $stderr];
}

/** @return array<string, string> */
function postgresBootstrapOptions(string $slug): array
{
    return [
        '--tenant' => $slug,
        '--unit' => 'matriz',
        '--name' => 'Caldas Centro',
        '--timezone' => 'America/Sao_Paulo',
        '--currency' => 'BRL',
        '--admin-name' => 'Administrador inicial',
        '--admin-email' => "{$slug}@example.test",
    ];
}

test('concurrent bootstrap commands converge without duplicate aggregate or events', function () {
    requirePostgresBootstrapGate();

    $slug = 'bootstrap-race-'.Str::lower(Str::random(16));
    $options = postgresBootstrapOptions($slug);
    $lock = postgresBootstrapPdo('migration');
    $lock->beginTransaction();
    $lock->exec('lock table app.tenants in share row exclusive mode');
    $first = startPostgresBootstrapProcess($options);
    $second = startPostgresBootstrapProcess($options);

    usleep(200_000);

    expect(proc_get_status($first[0])['running'])->toBeTrue()
        ->and(proc_get_status($second[0])['running'])->toBeTrue();

    $lock->commit();
    $results = [finishPostgresBootstrapProcess($first), finishPostgresBootstrapProcess($second)];
    $runtime = postgresBootstrapPdo();
    $tenant = $runtime->prepare('select id from app.tenants where slug = ?');
    $tenant->execute([$slug]);
    $tenantId = $tenant->fetchColumn();
    $userCount = $runtime->prepare('select count(*) from app.users where email_normalized = ?');
    $userCount->execute(["{$slug}@example.test"]);
    $ownerRole = $runtime->prepare("select id from app.roles where tenant_id = ? and key = 'owner' and is_system = true");
    $ownerRole->execute([$tenantId]);
    $membership = $runtime->prepare('select id from app.memberships where tenant_id = ? and status = ?');
    $membership->execute([$tenantId, 'active']);
    $membershipId = $membership->fetchColumn();
    $unitCount = $runtime->prepare('select count(*) from app.units where tenant_id = ?');
    $unitCount->execute([$tenantId]);
    $membershipCount = $runtime->prepare('select count(*) from app.memberships where tenant_id = ?');
    $membershipCount->execute([$tenantId]);
    $ownerAssignmentCount = $runtime->prepare("select count(*) from app.membership_roles where tenant_id = ? and membership_id = ? and role_id = ? and scope_kind = 'tenant' and unit_id is null and revoked_at is null");
    $ownerAssignmentCount->execute([$tenantId, $membershipId, $ownerRole->fetchColumn()]);
    $auditCount = $runtime->prepare("select count(*) from app.audit_events where tenant_id = ? and action = 'tenant.created'");
    $auditCount->execute([$tenantId]);
    $outboxCount = $runtime->prepare("select count(*) from app.outbox_events where tenant_id = ? and event_type = 'tenant.created'");
    $outboxCount->execute([$tenantId]);

    expect(collect($results)->every(static fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and(collect($results)->contains(static fn (array $result): bool => str_contains($result['stdout'], "Tenant bootstrapped: {$slug}")))->toBeTrue()
        ->and(collect($results)->contains(static fn (array $result): bool => str_contains($result['stdout'], "Tenant already bootstrapped: {$slug}")))->toBeTrue()
        ->and($tenantId)->toBeString()->not->toBeEmpty()
        ->and((int) $userCount->fetchColumn())->toBe(1)
        ->and((int) $unitCount->fetchColumn())->toBe(1)
        ->and((int) $membershipCount->fetchColumn())->toBe(1)
        ->and((int) $ownerAssignmentCount->fetchColumn())->toBe(1)
        ->and((int) $auditCount->fetchColumn())->toBe(1)
        ->and((int) $outboxCount->fetchColumn())->toBe(1);
});

test('concurrent resume repairs emit one event for one effective mutation', function () {
    requirePostgresBootstrapGate();

    $slug = 'bootstrap-resume-'.Str::lower(Str::random(16));
    $options = postgresBootstrapOptions($slug);
    $initial = finishPostgresBootstrapProcess(startPostgresBootstrapProcess($options));
    $runtime = postgresBootstrapPdo();
    $tenant = $runtime->prepare('select id from app.tenants where slug = ?');
    $tenant->execute([$slug]);
    $tenantId = $tenant->fetchColumn();
    $runtime->prepare('delete from app.membership_units where tenant_id = ?')->execute([$tenantId]);

    $lock = postgresBootstrapPdo('migration');
    $lock->beginTransaction();
    $lock->prepare('select id from app.tenants where id = ? for update')->execute([$tenantId]);
    $first = startPostgresBootstrapProcess($options + ['--resume' => true]);
    $second = startPostgresBootstrapProcess($options + ['--resume' => true]);

    usleep(200_000);

    expect($initial['exit_code'])->toBe(0)
        ->and(proc_get_status($first[0])['running'])->toBeTrue()
        ->and(proc_get_status($second[0])['running'])->toBeTrue();

    $lock->commit();
    $results = [finishPostgresBootstrapProcess($first), finishPostgresBootstrapProcess($second)];
    $membership = $runtime->prepare('select id from app.memberships where tenant_id = ? and status = ?');
    $membership->execute([$tenantId, 'active']);
    $membershipId = $membership->fetchColumn();
    $unitCount = $runtime->prepare('select count(*) from app.units where tenant_id = ?');
    $unitCount->execute([$tenantId]);
    $membershipUnitCount = $runtime->prepare('select count(*) from app.membership_units where tenant_id = ? and membership_id = ?');
    $membershipUnitCount->execute([$tenantId, $membershipId]);
    $auditCount = $runtime->prepare("select count(*) from app.audit_events where tenant_id = ? and action = 'tenant.onboarding.resumed'");
    $auditCount->execute([$tenantId]);
    $outboxCount = $runtime->prepare("select count(*) from app.outbox_events where tenant_id = ? and event_type = 'tenant.onboarding.resumed'");
    $outboxCount->execute([$tenantId]);

    expect(collect($results)->every(static fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and((int) $unitCount->fetchColumn())->toBe(1)
        ->and((int) $membershipUnitCount->fetchColumn())->toBe(1)
        ->and((int) $auditCount->fetchColumn())->toBe(1)
        ->and((int) $outboxCount->fetchColumn())->toBe(1);
});
