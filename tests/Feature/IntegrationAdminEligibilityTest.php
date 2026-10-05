<?php

use App\Actions\Identity\AssignRole;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\RevokeMembership;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Policies\IntegrationAdminPolicy;
use App\Support\OwnerPermissionCatalog;
use App\Support\TenantContext;

it('keeps profile access for every authenticated user and exposes the API settings only to a system owner', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Integration Owner Workspace']);
    TenantSubscription::factory()->create(['tenant_id' => $tenant->getKey(), 'status' => 'active', 'ends_at' => now()->addDays(14)]);

    $this->actingAs($owner)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('settings/profile')->missing('integrationCredentials')->missing('integrationOAuthGrants'));

    $this->actingAs($owner)
        ->get(route('settings.api'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('settings/api')->where('canManageIntegrations', true));

    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('settings/profile')->missing('integrationCredentials')->missing('integrationOAuthGrants'));

    $this->actingAs($regularUser)
        ->get(route('settings.api'))
        ->assertForbidden();
});

it('does not grant integration eligibility from tenant management or operational permissions', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Permission Workspace']);
    $policy = app(IntegrationAdminPolicy::class);

    foreach ([
        ['tenant.manage', 'role.manage'],
        ['calendar.manage'],
        ['integration.manage', 'integration.use', 'assistant.use'],
    ] as $permissionKeys) {
        $collaborator = User::factory()->create();
        $membership = Membership::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $collaborator->getKey(),
            'status' => 'active',
        ]);
        $role = Role::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'key' => 'custom-'.implode('-', $permissionKeys),
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permission = Permission::query()->firstOrCreate(
                ['key' => $permissionKey],
                ['description' => 'Test-only integration eligibility permission'],
            );
            RolePermission::query()->create([
                'tenant_id' => $tenant->getKey(),
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
            ]);
        }

        MembershipRole::factory()->forMembership($membership)->forRole($role)->create();
        $context = TenantContext::forUser($collaborator, $tenant->getKey());

        expect($policy->allows($collaborator, $context))->toBeFalse();

        $this->actingAs($collaborator)
            ->withHeader('X-Tenant-Id', $tenant->getKey())
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('settings/profile')->missing('integrationCredentials')->missing('integrationOAuthGrants'));

        $this->actingAs($collaborator)
            ->withHeader('X-Tenant-Id', $tenant->getKey())
            ->get(route('settings.api'))
            ->assertForbidden();
    }

    $ownerRole = Role::query()->where('tenant_id', $tenant->getKey())->where('key', 'owner')->firstOrFail();
    app(OwnerPermissionCatalog::class)->ensure($tenant, $ownerRole);

    expect($policy->allows($collaborator, TenantContext::forUser($collaborator, $tenant->getKey())))->toBeFalse();
});

it('rejects a foreign tenant context and a revoked owner membership', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Owner Workspace']);
    $foreignOwner = User::factory()->create();
    $foreignTenant = (new OnboardTenant)->handle($foreignOwner, ['name' => 'Foreign Workspace']);
    $policy = app(IntegrationAdminPolicy::class);
    $ownerContext = TenantContext::forUser($owner, $tenant->getKey());
    TenantSubscription::factory()->create(['tenant_id' => $tenant->getKey(), 'status' => 'active', 'ends_at' => now()->addDays(14)]);

    expect($policy->allows($owner, TenantContext::forUser($foreignOwner, $foreignTenant->getKey())))->toBeFalse()
        ->and($policy->allows($owner, $ownerContext))->toBeTrue();

    Membership::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'user_id' => $owner->getKey(),
        'status' => 'active',
    ]);
    expect($policy->allows($owner, TenantContext::forUser($owner, $foreignTenant->getKey())))->toBeFalse();

    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    (new RevokeMembership)->handle($owner, TenantContext::forUser($owner, $tenant->getKey()), $membership);

    expect($policy->allows($owner, $ownerContext))->toBeFalse();
});

it('does not expose integration controls without a valid tenant context or active SaaS access', function (): void {
    $userWithoutMembership = User::factory()->create();

    $this->actingAs($userWithoutMembership)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('settings/profile')->missing('integrationCredentials')->missing('integrationOAuthGrants'));

    $this->actingAs($userWithoutMembership)
        ->get(route('settings.api'))
        ->assertForbidden();

    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'No SaaS Workspace']);
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'cancelled',
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('settings/profile')->missing('integrationCredentials')->missing('integrationOAuthGrants'));

    $this->actingAs($owner)
        ->get(route('settings.api'))
        ->assertRedirect(route('billing.index'));
});

it('prevents a custom role manager from assigning the system owner role', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Role Escalation Workspace']);
    $manager = User::factory()->create();
    $managerMembership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $manager->getKey(),
        'status' => 'active',
    ]);
    $target = User::factory()->create();
    $targetMembership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $target->getKey(),
        'status' => 'active',
    ]);
    $managerRole = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'custom-role-manager',
    ]);

    foreach (['tenant.manage', 'role.manage'] as $permissionKey) {
        $permission = Permission::query()->where('key', $permissionKey)->firstOrFail();
        RolePermission::query()->create([
            'tenant_id' => $tenant->getKey(),
            'role_id' => $managerRole->getKey(),
            'permission_id' => $permission->getKey(),
        ]);
    }

    MembershipRole::factory()->forMembership($managerMembership)->forRole($managerRole)->create();
    $ownerRole = Role::query()->where('tenant_id', $tenant->getKey())->where('key', 'owner')->firstOrFail();

    expect(fn () => (new AssignRole)->handle(
        $manager,
        TenantContext::forUser($manager, $tenant->getKey()),
        $targetMembership,
        $ownerRole,
    ))->toThrow(LogicException::class);

    expect(MembershipRole::query()
        ->where('membership_id', $targetMembership->getKey())
        ->where('role_id', $ownerRole->getKey())
        ->whereNull('revoked_at')
        ->exists())->toBeFalse();
});
