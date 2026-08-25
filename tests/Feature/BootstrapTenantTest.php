<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\ResumeTenantOnboarding;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OwnerPermissionCatalog;
use App\Support\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function bootstrapTenantOptions(array $overrides = []): array
{
    return array_merge([
        '--tenant' => 'caldas-centro',
        '--unit' => 'matriz',
        '--name' => 'Caldas Centro',
        '--timezone' => 'America/Sao_Paulo',
        '--currency' => 'BRL',
        '--admin-name' => 'Administrador inicial',
        '--admin-email' => 'ADMIN@EXAMPLE.COM',
        '--no-interaction' => true,
    ], $overrides);
}

it('bootstraps a tenant with a normalized admin and synchronized owner catalog', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions())
        ->assertSuccessful()
        ->expectsOutputToContain('Tenant bootstrapped: caldas-centro');

    $admin = User::query()->where('email_normalized', 'admin@example.com')->firstOrFail();
    $tenant = Tenant::query()->where('slug', 'caldas-centro')->firstOrFail();
    $ownerRole = Role::query()->where('tenant_id', $tenant->getKey())->where('key', 'owner')->firstOrFail();
    $catalog = app(OwnerPermissionCatalog::class);

    expect(Hash::check('Secret123!', $admin->password))->toBeTrue()
        ->and($admin->email)->toBe('admin@example.com')
        ->and(Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $admin->getKey())->where('status', 'active')->exists())->toBeTrue()
        ->and(Permission::query()->whereIn('key', $catalog->keys())->count())->toBe(count($catalog->keys()))
        ->and(RolePermission::query()->where('tenant_id', $tenant->getKey())->where('role_id', $ownerRole->getKey())->count())->toBe(count($catalog->keys()))
        ->and(AuditEvent::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(OutboxEvent::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1);
});

it('does not leak bootstrap secrets or administrator PII to command output', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions())
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Secret123!')
        ->doesntExpectOutputToContain('ADMIN@EXAMPLE.COM')
        ->doesntExpectOutputToContain('Administrador inicial')
        ->expectsOutputToContain('Tenant bootstrapped: caldas-centro');
});

it('repeats an already complete bootstrap without duplicate rows or events', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');
    $options = bootstrapTenantOptions();

    $this->artisan('app:bootstrap-tenant', $options)->assertSuccessful();

    $counts = [
        'users' => User::query()->count(),
        'tenants' => Tenant::query()->count(),
        'memberships' => Membership::query()->count(),
        'audit' => AuditEvent::query()->count(),
        'outbox' => OutboxEvent::query()->count(),
    ];

    $this->artisan('app:bootstrap-tenant', $options)
        ->assertSuccessful()
        ->expectsOutputToContain('Tenant already bootstrapped: caldas-centro');

    expect(User::query()->count())->toBe($counts['users'])
        ->and(Tenant::query()->count())->toBe($counts['tenants'])
        ->and(Membership::query()->count())->toBe($counts['memberships'])
        ->and(AuditEvent::query()->count())->toBe($counts['audit'])
        ->and(OutboxEvent::query()->count())->toBe($counts['outbox']);
});

it('does not create or reassign an administrator when an existing slug has another owner', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');
    $owner = User::factory()->create();
    (new OnboardTenant)->handle($owner, [
        'name' => 'Existing Tenant',
        'slug' => 'caldas-centro',
    ]);

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions([
        '--admin-email' => 'other@example.com',
    ]))
        ->assertFailed()
        ->expectsOutputToContain('existing owner identity was not found');

    expect(User::query()->where('email_normalized', 'other@example.com')->exists())->toBeFalse();
});

it('requires a password for a new admin and has no production default', function () {
    app()->detectEnvironment(static fn (): string => 'production');
    Config::set('bootstrap.admin_password', null);

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions([
        '--force' => true,
    ]))
        ->assertFailed()
        ->expectsOutputToContain('No administrator password was provided');

    expect(User::query()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(0);
});

it('rejects an existing unverified administrator before changing tenant state', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');
    $admin = User::factory()->unverified()->create([
        'email' => 'admin@example.com',
        'password' => 'Original123!',
    ]);
    $originalHash = $admin->password;

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions())
        ->assertFailed()
        ->expectsOutputToContain('existing administrator identity is not verified')
        ->doesntExpectOutputToContain('admin@example.com')
        ->doesntExpectOutputToContain('Original123!');

    expect(Tenant::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1)
        ->and($admin->fresh()->password)->toBe($originalHash)
        ->and($admin->fresh()->email_verified_at)->toBeNull();
});

it('reuses a verified administrator without requiring or changing its password', function () {
    Config::set('bootstrap.admin_password', null);
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Original123!',
    ]);
    $originalHash = $admin->password;

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions())
        ->assertSuccessful()
        ->doesntExpectOutputToContain('admin@example.com')
        ->doesntExpectOutputToContain('Original123!')
        ->doesntExpectOutputToContain('Administrador inicial');

    expect(User::query()->count())->toBe(1)
        ->and($admin->fresh()->password)->toBe($originalHash)
        ->and($admin->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects a currency outside the explicit supported catalog', function () {
    Config::set('bootstrap.admin_password', 'Secret123!');

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions([
        '--currency' => 'ZZZ',
    ]))
        ->assertFailed()
        ->expectsOutputToContain('Currency is not supported in this release. Supported currencies: BRL.');

    expect(User::query()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(0);
});

it('requires force before a production bootstrap can run', function () {
    app()->detectEnvironment(static fn (): string => 'production');
    Config::set('bootstrap.admin_password', 'Secret123!');

    $this->artisan('app:bootstrap-tenant', bootstrapTenantOptions())
        ->assertFailed()
        ->expectsOutputToContain('Production bootstrap requires --force');

    expect(User::query()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(0);
});

it('seeds the local fixture only in local and testing environments', function () {
    app()->detectEnvironment(static fn (): string => 'production');
    app(DatabaseSeeder::class)->run();

    expect(User::query()->where('email_normalized', 'teste@caldas.local')->exists())->toBeFalse()
        ->and(Tenant::query()->where('slug', 'teste')->exists())->toBeFalse()
        ->and(Permission::query()->count())->toBe(0);

    app()->detectEnvironment(static fn (): string => 'local');
    app(DatabaseSeeder::class)->run();
    app(DatabaseSeeder::class)->run();

    expect(User::query()->where('email_normalized', 'teste@caldas.local')->count())->toBe(1)
        ->and(Tenant::query()->where('slug', 'teste')->count())->toBe(1)
        ->and(Permission::query()->whereIn('key', app(OwnerPermissionCatalog::class)->keys())->count())->toBe(count(app(OwnerPermissionCatalog::class)->keys()));
});

it('shares the complete bootstrap-safe Inertia contract', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Shared Contract']);

    $response = $this->actingAs($owner)->get(route('dashboard'));
    $props = $response->viewData('page')['props'];

    expect($props)->toHaveKeys(['schemaVersion', 'requestId', 'correlationId', 'auth', 'workspace', 'flash', 'ui'])
        ->and($props['schemaVersion'])->toBe(1)
        ->and($props['requestId'])->toBeString()->not->toBeEmpty()
        ->and($props['correlationId'])->toBeString()->not->toBeEmpty()
        ->and($props['auth'])->toHaveKeys(['user', 'permissions', 'entitlements'])
        ->and($props['auth']['user']['id'])->toBe($owner->getKey())
        ->and($props['auth']['permissions'])->toContain('tenant.manage')
        ->and($props['auth']['entitlements'])->toBe([])
        ->and($props['workspace'])->toHaveKeys(['tenant', 'activeUnit', 'availableUnits'])
        ->and($props['workspace']['tenant']['id'])->toBe($tenant->getKey())
        ->and($props['workspace']['activeUnit']['id'])->toBeString()
        ->and($props['workspace']['availableUnits'])->toHaveCount(1)
        ->and($props['flash'])->toHaveKeys(['success', 'info', 'warning', 'error'])
        ->and($props['ui'])->toHaveKey('sidebarOpen');
});

it('does not emit a resume event when the reconciliation is an effective no-op', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Resume Workspace']);
    $context = TenantContext::forUser($owner, $tenant->getKey());
    $auditCount = AuditEvent::query()->count();
    $outboxCount = OutboxEvent::query()->count();

    (new ResumeTenantOnboarding)->handle($owner, $context, $tenant, [
        'name' => 'Resume Workspace',
        'slug' => 'resume-workspace',
    ]);

    expect(AuditEvent::query()->count())->toBe($auditCount)
        ->and(OutboxEvent::query()->count())->toBe($outboxCount);
});

it('emits one resume event for a mutation and none for its repeat', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Resume Workspace']);
    $context = TenantContext::forUser($owner, $tenant->getKey());
    $auditCount = AuditEvent::query()->count();
    $outboxCount = OutboxEvent::query()->count();

    (new ResumeTenantOnboarding)->handle($owner, $context, $tenant, [
        'name' => 'Filial Centro',
        'slug' => 'filial-centro',
    ]);
    $afterMutationAudit = AuditEvent::query()->count();
    $afterMutationOutbox = OutboxEvent::query()->count();

    (new ResumeTenantOnboarding)->handle($owner, $context, $tenant, [
        'name' => 'Filial Centro',
        'slug' => 'filial-centro',
    ]);

    expect($afterMutationAudit)->toBe($auditCount + 1)
        ->and($afterMutationOutbox)->toBe($outboxCount + 1)
        ->and(AuditEvent::query()->count())->toBe($afterMutationAudit)
        ->and(OutboxEvent::query()->count())->toBe($afterMutationOutbox);
});

it('repairs stale owner catalog descriptions and assignments exactly', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Catalog Workspace']);
    $ownerRole = $tenant->roles()->where('key', 'owner')->firstOrFail();
    $catalog = app(OwnerPermissionCatalog::class);
    $permission = Permission::query()->where('key', 'tenant.view')->firstOrFail();
    $extra = Permission::factory()->create(['key' => 'catalog.extra']);

    $permission->forceFill(['description' => 'stale'])->save();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $ownerRole->getKey(),
        'permission_id' => $extra->getKey(),
        'created_at' => now(),
    ]);

    expect($catalog->ensure($tenant, $ownerRole))->toBeTrue();

    expect($permission->fresh()->description)->toBe('Owner catalog permission: tenant.view')
        ->and(RolePermission::query()->where('role_id', $ownerRole->getKey())->where('permission_id', $extra->getKey())->exists())->toBeFalse()
        ->and(RolePermission::query()->where('role_id', $ownerRole->getKey())->count())->toBe(count($catalog->keys()));
});

it('emits one resume event for a stale description without extra assignments', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Stale Catalog Workspace']);
    $context = TenantContext::forUser($owner, $tenant->getKey());
    $catalog = app(OwnerPermissionCatalog::class);
    $permission = Permission::query()->where('key', 'tenant.view')->firstOrFail();
    $ownerRole = $tenant->roles()->where('key', 'owner')->firstOrFail();
    $permission->forceFill(['description' => 'stale'])->save();
    $auditCount = AuditEvent::query()->count();
    $outboxCount = OutboxEvent::query()->count();

    (new ResumeTenantOnboarding)->handle($owner, $context, $tenant, [
        'name' => 'Stale Catalog Workspace',
        'slug' => 'stale-catalog-workspace',
    ]);

    expect($permission->fresh()->description)->toBe('Owner catalog permission: tenant.view')
        ->and(RolePermission::query()->where('role_id', $ownerRole->getKey())->count())->toBe(count($catalog->keys()))
        ->and(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(OutboxEvent::query()->count())->toBe($outboxCount + 1);

    (new ResumeTenantOnboarding)->handle($owner, $context, $tenant, [
        'name' => 'Stale Catalog Workspace',
        'slug' => 'stale-catalog-workspace',
    ]);

    expect(AuditEvent::query()->count())->toBe($auditCount + 1)
        ->and(OutboxEvent::query()->count())->toBe($outboxCount + 1);
});

it('rolls back tenant, audit and outbox when event recording fails', function () {
    $owner = User::factory()->create();
    $failOutboxInsert = true;
    DB::listen(function (QueryExecuted $query) use (&$failOutboxInsert): void {
        if ($failOutboxInsert && str_contains(strtolower($query->sql), 'outbox_events')) {
            $failOutboxInsert = false;
            throw new RuntimeException('event sink failure');
        }
    });

    expect(fn () => (new OnboardTenant)->handle($owner, ['name' => 'Rollback Workspace']))
        ->toThrow(RuntimeException::class, 'event sink failure');

    expect(Tenant::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0)
        ->and(OutboxEvent::query()->count())->toBe(0);
});

it('rolls back the local seed user and catalog when onboarding fails', function () {
    app()->detectEnvironment(static fn (): string => 'local');
    $failOutboxInsert = true;
    DB::listen(function (QueryExecuted $query) use (&$failOutboxInsert): void {
        if ($failOutboxInsert && str_contains(strtolower($query->sql), 'outbox_events')) {
            $failOutboxInsert = false;
            throw new RuntimeException('seed event sink failure');
        }
    });

    expect(fn () => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'seed event sink failure');

    expect(User::query()->count())->toBe(0)
        ->and(Tenant::query()->count())->toBe(0)
        ->and(Permission::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0)
        ->and(OutboxEvent::query()->count())->toBe(0);
});
