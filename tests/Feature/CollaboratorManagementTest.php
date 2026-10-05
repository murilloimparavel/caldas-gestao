<?php

use App\Actions\Identity\OnboardTenant;
use App\Enums\UnitStatus;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CollaboratorAccessNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    $linkedAudit = AuditEvent::query()->where('action', 'membership.professional_linked')->where('resource_id', $membership->getKey())->sole();
    expect($linkedAudit->metadata['membership_id'])->toBe($membership->getKey())
        ->and($linkedAudit->metadata['professional_id'])->toBe($professional->getKey())
        ->and($linkedAudit->metadata['previous_professional_id'])->toBeNull();
    $linkedOutbox = OutboxEvent::query()->where('event_type', 'membership.professional_linked')->where('aggregate_id', $membership->getKey())->sole();
    expect($linkedOutbox->payload['membership_id'])->toBe($membership->getKey())
        ->and($linkedOutbox->payload['professional_id'])->toBe($professional->getKey())
        ->and($linkedOutbox->payload['previous_professional_id'])->toBeNull();

    $this->actingAs($owner)->patch(route('settings.collaborators.professional.update', $membership), ['professional_id' => null])->assertRedirect();
    expect($membership->fresh()->professional_id)->toBeNull();

    $unlinkedAudit = AuditEvent::query()->where('action', 'membership.professional_unlinked')->where('resource_id', $membership->getKey())->sole();
    expect($unlinkedAudit->metadata['professional_id'])->toBeNull()
        ->and($unlinkedAudit->metadata['previous_professional_id'])->toBe($professional->getKey());
    $unlinkedOutbox = OutboxEvent::query()->where('event_type', 'membership.professional_unlinked')->where('aggregate_id', $membership->getKey())->sole();
    expect($unlinkedOutbox->payload['professional_id'])->toBeNull()
        ->and($unlinkedOutbox->payload['previous_professional_id'])->toBe($professional->getKey());
});

it('adds a verified existing user and activates the membership', function () {
    Notification::fake();
    [$owner, $tenant, $unit] = collaboratorWorkspace();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => $user->name, 'email' => $user->email])
        ->assertRedirect(route('settings.collaborators'));

    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->firstOrFail();
    expect($membership->status->value)->toBe('active')
        ->and($membership->membershipUnits()->where('unit_id', $unit->getKey())->exists())->toBeTrue();
    Notification::assertSentTo($user, CollaboratorAccessNotification::class);
    Notification::assertNotSentTo($user, ResetPassword::class);
});

it('shows a warning when the access email is throttled', function () {
    Notification::fake();
    Password::shouldReceive('broker')->once()->andReturnSelf();
    Password::shouldReceive('sendResetLink')->once()->andReturn(Password::RESET_THROTTLED);
    [$owner, $tenant] = collaboratorWorkspace();

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => 'Throttled', 'email' => 'throttled@example.test'])
        ->assertRedirect(route('settings.collaborators'))
        ->assertSessionHas('warning');

    $user = User::query()->where('email_normalized', 'throttled@example.test')->firstOrFail();
    expect(Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->exists())->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects inviting the current user or an already active member', function () {
    Notification::fake();
    [$owner, $tenant] = collaboratorWorkspace();
    $activeUser = User::factory()->create(['email_verified_at' => now()]);
    Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $activeUser->getKey(), 'status' => 'active']);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => $owner->name, 'email' => $owner->email])
        ->assertSessionHasErrors('email');
    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => $activeUser->name, 'email' => $activeUser->email])
        ->assertSessionHasErrors('email');

    expect(Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $activeUser->getKey())->count())->toBe(1);
    Notification::assertNothingSent();
});

it('creates a new collaborator identity without granting access before inbox verification', function () {
    Notification::fake();
    [$owner, $tenant] = collaboratorWorkspace();

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => 'Novo Barbeiro', 'email' => 'novo@example.test'])
        ->assertRedirect(route('settings.collaborators'));

    $user = User::query()->where('email_normalized', 'novo@example.test')->firstOrFail();
    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->firstOrFail();
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTo($user, VerifyEmail::class);
    expect($membership->status->value)->toBe('invited')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'membership.invite.sent')->where('resource_id', $membership->getKey())->exists())->toBeTrue();
});

it('resends invited collaborator access and records the dispatch', function () {
    Notification::fake();
    [$owner, $tenant] = collaboratorWorkspace();
    $user = User::factory()->unverified()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'invited']);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.access.resend', $membership))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTo($user, VerifyEmail::class);
    expect(AuditEvent::query()->where('action', 'membership.access.resent')->where('resource_id', $membership->getKey())->exists())->toBeTrue();
});

it('activates the invited collaborator after email verification', function () {
    Notification::fake();
    [$owner, $tenant] = collaboratorWorkspace();
    $user = User::factory()->unverified()->create();

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => $user->name, 'email' => $user->email])
        ->assertRedirect();

    $user->forceFill(['email_verified_at' => now()])->save();
    Event::dispatch(new Verified($user));

    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->firstOrFail();
    expect($membership->fresh()->status->value)->toBe('active')
        ->and(AuditEvent::query()->where('action', 'membership.activated')->where('resource_id', $membership->getKey())->exists())->toBeTrue();
});

it('does not activate an invited membership whose unit is inactive', function () {
    Notification::fake();
    [$owner, $tenant, $unit] = collaboratorWorkspace();
    $unit->forceFill(['status' => UnitStatus::Inactive])->save();
    $user = User::factory()->unverified()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'invited']);
    MembershipUnit::factory()->create(['tenant_id' => $tenant->getKey(), 'membership_id' => $membership->getKey(), 'unit_id' => $unit->getKey(), 'is_primary' => true]);

    $user->forceFill(['email_verified_at' => now()])->save();
    Event::dispatch(new Verified($user));

    expect($membership->fresh()->status->value)->toBe('invited');
});

it('reinvites a revoked collaborator and keeps access gated until verification', function () {
    Notification::fake();
    [$owner, $tenant] = collaboratorWorkspace();
    $user = User::factory()->unverified()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'status' => 'revoked',
        'revoked_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.collaborators.store'), ['name' => $user->name, 'email' => $user->email])
        ->assertRedirect();

    expect($membership->fresh()->status->value)->toBe('invited')
        ->and(AuditEvent::query()->where('action', 'membership.reinvited')->where('resource_id', $membership->getKey())->exists())->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTo($user, VerifyEmail::class);
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
