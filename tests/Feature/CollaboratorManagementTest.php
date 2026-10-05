<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function collaboratorWorkspace(): array
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Collaborators '.Str::random(8), 'slug' => 'collaborators-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('allows an owner to create a custom profile and view collaborators', function () {
    [$owner, $tenant] = collaboratorWorkspace();
    $permission = Permission::query()->where('key', 'calendar.view')->firstOrFail();

    $this->actingAs($owner)->post(route('settings.collaborators.roles.store'), [
        'name' => 'Barbeiro', 'description' => 'Acesso à própria operação', 'permission_ids' => [$permission->getKey()],
    ])->assertRedirect(route('settings.collaborators'));

    $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Barbeiro')->firstOrFail();
    $longName = trim(str_repeat('Perfil de acesso ', 7));
    $this->actingAs($owner)->post(route('settings.collaborators.roles.store'), ['name' => $longName])->assertRedirect();
    $longRole = Role::query()->where('tenant_id', $tenant->getKey())->where('name', $longName)->firstOrFail();
    expect(strlen($longRole->key))->toBeLessThanOrEqual(80);
    expect(RolePermission::query()->where('role_id', $role->getKey())->where('permission_id', $permission->getKey())->exists())->toBeTrue();
    expect(AuditEvent::query()->where('action', 'role.permissions.updated')->where('resource_id', $role->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('settings.collaborators'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/collaborators')
            ->has('memberships')
            ->where('canManage', true));

});

it('links a membership to at most one professional', function () {
    [$owner, $tenant, $unit] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active']);
    MembershipUnit::factory()->create(['tenant_id' => $tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $unit->getKey(), 'is_primary' => true]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $this->actingAs($owner)->patch(route('settings.collaborators.professional.update', $membership), ['professional_id' => $professional->getKey()])->assertRedirect();
    expect($membership->fresh()->professional_id)->toBe($professional->getKey());
});

it('adds a verified existing user and activates the membership', function () {
    [$owner, $tenant, $unit] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['email' => $user->email])
        ->assertRedirect(route('settings.collaborators'));

    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->firstOrFail();
    expect($membership->status->value)->toBe('active')
        ->and($membership->membershipUnits()->where('unit_id', $unit->getKey())->exists())->toBeTrue();
});

it('assigns a custom profile to an active membership', function () {
    [$owner, $tenant] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active']);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Barbeiro']);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.role.assign', $membership), ['role_id' => $role->getKey(), 'scope_kind' => 'tenant'])
        ->assertRedirect();

    expect($membership->fresh()->roles()->whereKey($role->getKey())->exists())->toBeTrue();
});

it('rejects administrative permissions from custom profiles', function () {
    [$owner, $tenant] = collaboratorWorkspace();
    $permission = Permission::query()->where('key', 'role.manage')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('settings.collaborators.roles.store'), ['name' => 'Escalador', 'permission_ids' => [$permission->getKey()]])
        ->assertStatus(422);

    expect(Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Escalador')->exists())->toBeFalse();
});

it('rejects a professional outside the membership unit or tenant', function () {
    [$owner, $tenant, $unit] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active']);
    MembershipUnit::factory()->create(['tenant_id' => $tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $unit->getKey(), 'is_primary' => true]);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $otherProfessional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $otherUnit->getKey()]);
    $foreignTenant = collaboratorWorkspace()[1];
    $foreignProfessional = Professional::factory()->create(['tenant_id' => $foreignTenant->getKey(), 'unit_id' => $foreignTenant->units()->firstOrFail()->getKey()]);

    $this->actingAs($owner)
        ->patch(route('settings.collaborators.professional.update', $membership), ['professional_id' => $otherProfessional->getKey()])
        ->assertStatus(422);

    $this->actingAs($owner)
        ->patch(route('settings.collaborators.professional.update', $membership), ['professional_id' => $foreignProfessional->getKey()])
        ->assertNotFound();
});

it('allows an authorized manager to revoke collaborator access', function () {
    [$owner, $tenant] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active']);

    $this->actingAs($owner)
        ->delete(route('settings.collaborators.revoke', $membership))
        ->assertRedirect(route('settings.collaborators'));

    expect($membership->fresh()->status->value)->toBe('revoked');
});
