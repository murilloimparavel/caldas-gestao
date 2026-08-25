<?php

use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function requirePostgresTenancyGate(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('PostgreSQL constraints are enforced by the PostgreSQL CI job.');
    }
}

function postgresTenancyRuntimePdo(): PDO
{
    /** @var array{host:string,port:int|string,database:string,username:string,password:string} $connection */
    $connection = config('database.connections.pgsql');

    return new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $connection['host'], $connection['port'], $connection['database']),
        $connection['username'],
        $connection['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

function postgresTenancyMigrationPdo(): PDO
{
    /** @var array{host:string,port:int|string,database:string,username:string,password:string} $connection */
    $connection = config('database.connections.migration');

    return new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $connection['host'], $connection['port'], $connection['database']),
        $connection['username'],
        $connection['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

/** @return array{tenant_id:string,membership_id:string} */
function seedPostgresMembership(PDO $connection): array
{
    $tenantId = (string) Str::uuid7();
    $userId = (string) Str::uuid7();
    $membershipId = (string) Str::uuid7();

    $connection->prepare(
        "insert into app.users (id, name, email, email_normalized, password, created_at, updated_at) values (?, 'Race User', ?, ?, 'password', now(), now())",
    )->execute([$userId, "race-{$userId}@example.test", "race-{$userId}@example.test"]);
    $connection->prepare(
        "insert into app.tenants (id, slug, name, status, timezone, default_currency, lock_version, created_at, updated_at) values (?, ?, 'Race tenant', 'active', 'UTC', 'BRL', 0, now(), now())",
    )->execute([$tenantId, 'race-'.Str::lower(Str::random(18))]);
    $connection->prepare(
        "insert into app.memberships (id, tenant_id, user_id, status, joined_at, revoked_at, lock_version, created_at, updated_at) values (?, ?, ?, 'active', now(), null, 0, now(), now())",
    )->execute([$membershipId, $tenantId, $userId]);

    return ['tenant_id' => $tenantId, 'membership_id' => $membershipId];
}

/**
 * @return array{owner_id:string,target_user_id:string,tenant_id:string,target_membership_id:string,role_id:string}
 */
function seedPostgresActionWorkspace(PDO $connection, string $targetStatus = 'active'): array
{
    $ownerId = (string) Str::uuid7();
    $targetUserId = (string) Str::uuid7();
    $tenantId = (string) Str::uuid7();
    $unitId = (string) Str::uuid7();
    $ownerMembershipId = (string) Str::uuid7();
    $targetMembershipId = (string) Str::uuid7();
    $ownerRoleId = (string) Str::uuid7();
    $roleId = (string) Str::uuid7();
    $ownerEmail = "owner-{$ownerId}@example.test";
    $targetEmail = "target-{$targetUserId}@example.test";

    $connection->prepare(
        "insert into app.users (id, name, email, email_normalized, password, email_verified_at, created_at, updated_at) values (?, 'Action owner', ?, ?, 'password', now(), now(), now()), (?, 'Action target', ?, ?, 'password', now(), now(), now())",
    )->execute([$ownerId, $ownerEmail, $ownerEmail, $targetUserId, $targetEmail, $targetEmail]);
    $connection->prepare(
        "insert into app.tenants (id, slug, name, status, timezone, default_currency, lock_version, created_at, updated_at) values (?, ?, 'Action tenant', 'active', 'UTC', 'BRL', 0, now(), now())",
    )->execute([$tenantId, 'action-'.Str::lower(Str::random(18))]);
    $connection->prepare(
        "insert into app.units (id, tenant_id, slug, name, status, lock_version, created_at, updated_at) values (?, ?, 'action-unit', 'Action unit', 'active', 0, now(), now())",
    )->execute([$unitId, $tenantId]);
    $connection->prepare(
        "insert into app.memberships (id, tenant_id, user_id, status, joined_at, revoked_at, lock_version, created_at, updated_at) values (?, ?, ?, 'active', now(), null, 0, now(), now()), (?, ?, ?, ?, null, null, 0, now(), now())",
    )->execute([$ownerMembershipId, $tenantId, $ownerId, $targetMembershipId, $tenantId, $targetUserId, $targetStatus]);
    $connection->prepare(
        'insert into app.membership_units (tenant_id, membership_id, unit_id, is_primary, created_at) values (?, ?, ?, true, now()), (?, ?, ?, true, now())',
    )->execute([$tenantId, $ownerMembershipId, $unitId, $tenantId, $targetMembershipId, $unitId]);
    $connection->prepare(
        "insert into app.roles (id, tenant_id, key, name, is_system, lock_version, created_at, updated_at) values (?, ?, 'owner', 'Owner', true, 0, now(), now()), (?, ?, 'manager', 'Manager', false, 0, now(), now())",
    )->execute([$ownerRoleId, $tenantId, $roleId, $tenantId]);

    foreach (['tenant.manage', 'membership.manage', 'role.manage'] as $key) {
        $connection->prepare(
            "insert into app.permissions (id, key, description, created_at, updated_at) values (?, ?, 'Action test permission', now(), now()) on conflict (key) do nothing",
        )->execute([(string) Str::uuid7(), $key]);
        $permission = $connection->prepare('select id from app.permissions where key = ?');
        $permission->execute([$key]);
        $connection->prepare(
            'insert into app.role_permissions (tenant_id, role_id, permission_id, created_at) values (?, ?, ?, now())',
        )->execute([$tenantId, $ownerRoleId, $permission->fetchColumn()]);
    }

    $connection->prepare(
        "insert into app.membership_roles (id, tenant_id, membership_id, role_id, scope_kind, assignment_scope, unit_id, lock_version, revoked_at, created_at) values (?, ?, ?, ?, 'tenant', 'tenant', null, 0, null, now())",
    )->execute([(string) Str::uuid7(), $tenantId, $ownerMembershipId, $ownerRoleId]);

    return [
        'owner_id' => $ownerId,
        'target_user_id' => $targetUserId,
        'tenant_id' => $tenantId,
        'target_membership_id' => $targetMembershipId,
        'role_id' => $roleId,
    ];
}

/** @return array{0:resource,1:array<int,resource>} */
function startIdentityActionProcess(string $script): array
{
    $process = proc_open(
        [PHP_BINARY, base_path('artisan'), 'tinker', '--execute', $script],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        base_path(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated Laravel action process.');
    }

    return [$process, $pipes];
}

/** @param array{0:resource,1:array<int,resource>} $process */
function finishIdentityActionProcess(array $process): array
{
    [$resource, $pipes] = $process;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit_code' => proc_close($resource), 'stdout' => $stdout, 'stderr' => $stderr];
}

/** @param array{owner_id:string,tenant_id:string,target_membership_id:string,role_id:string} $workspace */
function identityActionScript(string $action, array $workspace): string
{
    $ownerId = var_export($workspace['owner_id'], true);
    $tenantId = var_export($workspace['tenant_id'], true);
    $membershipId = var_export($workspace['target_membership_id'], true);
    $roleId = var_export($workspace['role_id'], true);

    return match ($action) {
        'activate' => <<<PHP
            \$owner = \App\Models\User::query()->findOrFail({$ownerId});
            \$membership = \App\Models\Membership::query()->findOrFail({$membershipId});
            \$context = \App\Support\TenantContext::forUser(\$owner, {$tenantId});
            (new \App\Actions\Identity\ActivateMembership)->handle(\$owner, \$context, \$membership);
            echo 'OK';
            PHP,
        'assign' => <<<PHP
            \$owner = \App\Models\User::query()->findOrFail({$ownerId});
            \$membership = \App\Models\Membership::query()->findOrFail({$membershipId});
            \$role = \App\Models\Role::query()->findOrFail({$roleId});
            \$context = \App\Support\TenantContext::forUser(\$owner, {$tenantId});
            (new \App\Actions\Identity\AssignRole)->handle(\$owner, \$context, \$membership, \$role);
            echo 'OK';
            PHP,
        'revoke-membership' => <<<PHP
            \$owner = \App\Models\User::query()->findOrFail({$ownerId});
            \$membership = \App\Models\Membership::query()->findOrFail({$membershipId});
            \$context = \App\Support\TenantContext::forUser(\$owner, {$tenantId});
            (new \App\Actions\Identity\RevokeMembership)->handle(\$owner, \$context, \$membership);
            echo 'OK';
            PHP,
        'revoke-role' => <<<PHP
            \$owner = \App\Models\User::query()->findOrFail({$ownerId});
            \$assignment = \App\Models\MembershipRole::query()->where('membership_id', {$membershipId})->where('role_id', {$roleId})->whereNull('revoked_at')->firstOrFail();
            \$context = \App\Support\TenantContext::forUser(\$owner, {$tenantId});
            (new \App\Actions\Identity\RevokeRole)->handle(\$owner, \$context, \$assignment);
            echo 'OK';
            PHP,
        default => throw new InvalidArgumentException("Unsupported action [{$action}]."),
    };
}

test('enforces active role uniqueness with PostgreSQL NULLS NOT DISTINCT', function () {
    requirePostgresTenancyGate();

    $tenant = Tenant::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);

    MembershipRole::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'role_id' => $role->getKey(),
        'scope_kind' => 'tenant',
        'assignment_scope' => 'tenant',
        'unit_id' => null,
    ]);

    expect(fn () => DB::transaction(function () use ($tenant, $membership, $role): void {
        MembershipRole::query()->create([
            'tenant_id' => $tenant->getKey(),
            'membership_id' => $membership->getKey(),
            'role_id' => $role->getKey(),
            'scope_kind' => 'tenant',
            'assignment_scope' => 'tenant',
            'unit_id' => null,
        ]);
    }))->toThrow(QueryException::class);

    $index = DB::selectOne(
        "select indexdef from pg_indexes where schemaname = current_schema() and indexname = 'membership_roles_active_unique'",
    );

    expect($index)->not->toBeNull()
        ->and((string) $index->indexdef)->toContain('NULLS NOT DISTINCT')
        ->toContain('WHERE (revoked_at IS NULL)');
});

test('rejects cross-tenant references across every tenancy RBAC pivot in PostgreSQL', function () {
    requirePostgresTenancyGate();

    $tenant = Tenant::factory()->create();
    $foreignTenant = Tenant::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignMembership = Membership::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignRole = Role::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $permission = Permission::factory()->create();

    expect(fn () => DB::transaction(function () use ($tenant, $foreignMembership, $unit): void {
        MembershipUnit::query()->create([
            'tenant_id' => $tenant->getKey(),
            'membership_id' => $foreignMembership->getKey(),
            'unit_id' => $unit->getKey(),
            'is_primary' => false,
        ]);
    }))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(function () use ($tenant, $foreignRole, $permission): void {
            RolePermission::query()->create([
                'tenant_id' => $tenant->getKey(),
                'role_id' => $foreignRole->getKey(),
                'permission_id' => $permission->getKey(),
            ]);
        }))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(function () use ($tenant, $membership, $role, $foreignUnit): void {
            MembershipRole::query()->create([
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'role_id' => $role->getKey(),
                'scope_kind' => 'unit',
                'assignment_scope' => 'unit:'.$foreignUnit->getKey(),
                'unit_id' => $foreignUnit->getKey(),
            ]);
        }))->toThrow(QueryException::class);
});

test('enforces PostgreSQL CHECK constraints for tenancy and RBAC state', function () {
    requirePostgresTenancyGate();

    $tenant = Tenant::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $now = now();

    expect(fn () => DB::transaction(function () use ($now): void {
        DB::table((new Tenant)->getTable())->insert([
            'id' => (string) Str::uuid7(),
            'slug' => 'invalid-status-'.Str::lower(Str::random(12)),
            'name' => 'Invalid status',
            'status' => 'not-a-tenant-status',
            'timezone' => 'UTC',
            'default_currency' => 'BRL',
            'lock_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(function () use ($now): void {
            Permission::query()->create([
                'key' => 'invalid permission key',
                'description' => 'Must fail the database constraint.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(function () use ($tenant, $membership, $role): void {
            MembershipRole::query()->create([
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'role_id' => $role->getKey(),
                'scope_kind' => 'unit',
                'assignment_scope' => 'tenant',
                'unit_id' => null,
            ]);
        }))->toThrow(QueryException::class);
});

test('keeps runtime DML available for tenant records without granting DDL', function () {
    requirePostgresTenancyGate();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($user->exists)->toBeTrue()
        ->and($tenant->exists)->toBeTrue()
        ->and(DB::selectOne("select has_schema_privilege(current_user, current_schema(), 'CREATE') as can_create")->can_create)->toBeFalse();
});

test('detects concurrent create and membership mutation conflicts in independent PostgreSQL runtime sessions', function () {
    requirePostgresTenancyGate();

    $seed = postgresTenancyRuntimePdo();
    $ids = seedPostgresMembership($seed);
    $first = postgresTenancyRuntimePdo();
    $second = postgresTenancyRuntimePdo();
    $slug = 'concurrent-create-'.Str::lower(Str::random(18));

    $first->beginTransaction();
    $second->beginTransaction();
    $first->prepare('select id from app.tenants where slug = ?')->execute([$slug]);
    $second->prepare('select id from app.tenants where slug = ?')->execute([$slug]);
    $first->prepare(
        "insert into app.tenants (id, slug, name, status, timezone, default_currency, lock_version, created_at, updated_at) values (?, ?, 'Concurrent tenant', 'active', 'UTC', 'BRL', 0, now(), now())",
    )->execute([(string) Str::uuid7(), $slug]);
    $first->commit();

    expect(fn () => $second->prepare(
        "insert into app.tenants (id, slug, name, status, timezone, default_currency, lock_version, created_at, updated_at) values (?, ?, 'Concurrent tenant', 'active', 'UTC', 'BRL', 0, now(), now())",
    )->execute([(string) Str::uuid7(), $slug]))->toThrow(PDOException::class);
    $second->rollBack();

    $first->beginTransaction();
    $second->beginTransaction();
    $first->exec('set transaction isolation level serializable');
    $second->exec('set transaction isolation level serializable');
    $first->prepare('select lock_version from app.memberships where id = ?')->execute([$ids['membership_id']]);
    $second->prepare('select lock_version from app.memberships where id = ?')->execute([$ids['membership_id']]);
    $first->prepare('update app.memberships set lock_version = lock_version + 1 where id = ?')->execute([$ids['membership_id']]);
    $first->commit();

    try {
        $second->prepare('update app.memberships set lock_version = lock_version + 1 where id = ?')->execute([$ids['membership_id']]);
        $second->commit();
        $this->fail('The stale serializable transaction unexpectedly committed.');
    } catch (PDOException $exception) {
        if ($second->inTransaction()) {
            $second->rollBack();
        }

        expect($exception->getCode())->toBe('40001');
    }

    $retry = postgresTenancyRuntimePdo();
    $retry->beginTransaction();
    $retry->prepare('update app.memberships set lock_version = lock_version + 1 where id = ?')->execute([$ids['membership_id']]);
    $retry->commit();

    $version = $retry->prepare('select lock_version from app.memberships where id = ?');
    $version->execute([$ids['membership_id']]);

    expect((int) $version->fetchColumn())->toBe(2);
});

test('serializes duplicate membership activation through the ActivateMembership action', function () {
    requirePostgresTenancyGate();

    $workspace = seedPostgresActionWorkspace(postgresTenancyRuntimePdo(), 'invited');
    $lock = postgresTenancyRuntimePdo();
    $lock->beginTransaction();
    $lock->prepare('select id from app.tenants where id = ? for update')->execute([$workspace['tenant_id']]);
    $first = startIdentityActionProcess(identityActionScript('activate', $workspace));
    $second = startIdentityActionProcess(identityActionScript('activate', $workspace));

    usleep(200_000);

    expect(proc_get_status($first[0])['running'])->toBeTrue()
        ->and(proc_get_status($second[0])['running'])->toBeTrue();

    $lock->commit();
    $results = [finishIdentityActionProcess($first), finishIdentityActionProcess($second)];
    $successfulActivations = collect($results)->where('exit_code', 0)->count();
    $membership = postgresTenancyRuntimePdo()->prepare('select status, lock_version from app.memberships where id = ?');
    $membership->execute([$workspace['target_membership_id']]);

    expect($successfulActivations)->toBe(1)
        ->and($membership->fetch(PDO::FETCH_ASSOC))->toBe(['status' => 'active', 'lock_version' => 1]);
});

test('keeps AssignRole idempotent and preserves history after RevokeRole through real actions', function () {
    requirePostgresTenancyGate();

    $workspace = seedPostgresActionWorkspace(postgresTenancyRuntimePdo());
    $lock = postgresTenancyRuntimePdo();
    $lock->beginTransaction();
    $lock->prepare('select id from app.tenants where id = ? for update')->execute([$workspace['tenant_id']]);
    $first = startIdentityActionProcess(identityActionScript('assign', $workspace));
    $second = startIdentityActionProcess(identityActionScript('assign', $workspace));

    usleep(200_000);
    $lock->commit();
    $results = [finishIdentityActionProcess($first), finishIdentityActionProcess($second)];
    $connection = postgresTenancyRuntimePdo();
    $activeAssignments = $connection->prepare('select count(*) from app.membership_roles where membership_id = ? and role_id = ? and revoked_at is null');
    $activeAssignments->execute([$workspace['target_membership_id'], $workspace['role_id']]);

    expect(collect($results)->every(fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and((int) $activeAssignments->fetchColumn())->toBe(1)
        ->and(finishIdentityActionProcess(startIdentityActionProcess(identityActionScript('revoke-role', $workspace)))['exit_code'])->toBe(0)
        ->and(finishIdentityActionProcess(startIdentityActionProcess(identityActionScript('assign', $workspace)))['exit_code'])->toBe(0);

    $history = $connection->prepare('select count(*) filter (where revoked_at is not null) as revoked, count(*) filter (where revoked_at is null) as active from app.membership_roles where membership_id = ? and role_id = ?');
    $history->execute([$workspace['target_membership_id'], $workspace['role_id']]);

    expect($history->fetch(PDO::FETCH_ASSOC))->toBe(['revoked' => 1, 'active' => 1]);
});

test('leaves no active role when RevokeMembership races AssignRole through real actions', function () {
    requirePostgresTenancyGate();

    $workspace = seedPostgresActionWorkspace(postgresTenancyRuntimePdo());
    $lock = postgresTenancyRuntimePdo();
    $lock->beginTransaction();
    $lock->prepare('select id from app.tenants where id = ? for update')->execute([$workspace['tenant_id']]);
    $revoke = startIdentityActionProcess(identityActionScript('revoke-membership', $workspace));
    $assign = startIdentityActionProcess(identityActionScript('assign', $workspace));

    usleep(200_000);
    $lock->commit();
    $results = [finishIdentityActionProcess($revoke), finishIdentityActionProcess($assign)];
    $connection = postgresTenancyRuntimePdo();
    $membership = $connection->prepare('select status from app.memberships where id = ?');
    $membership->execute([$workspace['target_membership_id']]);
    $activeAssignments = $connection->prepare('select count(*) from app.membership_roles where membership_id = ? and revoked_at is null');
    $activeAssignments->execute([$workspace['target_membership_id']]);

    expect(collect($results)->contains(fn (array $result): bool => $result['exit_code'] === 0))->toBeTrue()
        ->and($membership->fetchColumn())->toBe('revoked')
        ->and((int) $activeAssignments->fetchColumn())->toBe(0);
});

test('retries a transient serialization failure inside AssignRole', function () {
    requirePostgresTenancyGate();

    $workspace = seedPostgresActionWorkspace(postgresTenancyRuntimePdo());
    $migration = postgresTenancyMigrationPdo();
    $migration->exec('create sequence app.assign_role_retry_probe');
    $migration->exec(<<<'SQL'
        create function app.assign_role_retry_once() returns trigger language plpgsql as $$
        begin
            if nextval('app.assign_role_retry_probe') = 1 then
                raise exception 'transient action retry probe' using errcode = '40001';
            end if;

            return new;
        end;
        $$
        SQL);
    $migration->exec('create trigger assign_role_retry_probe before insert on app.membership_roles for each row execute function app.assign_role_retry_once()');

    try {
        $result = finishIdentityActionProcess(startIdentityActionProcess(identityActionScript('assign', $workspace)));
    } finally {
        $migration->exec('drop trigger if exists assign_role_retry_probe on app.membership_roles');
        $migration->exec('drop function if exists app.assign_role_retry_once()');
        $migration->exec('drop sequence if exists app.assign_role_retry_probe');
    }

    $assignment = postgresTenancyRuntimePdo()->prepare('select count(*) from app.membership_roles where membership_id = ? and role_id = ? and revoked_at is null');
    $assignment->execute([$workspace['target_membership_id'], $workspace['role_id']]);

    expect($result['exit_code'])->toBe(0)
        ->and((int) $assignment->fetchColumn())->toBe(1);
});
