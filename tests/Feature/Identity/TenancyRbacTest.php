<?php

use App\Actions\Identity\ActivateMembership;
use App\Actions\Identity\AssignRole;
use App\Actions\Identity\InviteMembership;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\ResumeTenantOnboarding;
use App\Actions\Identity\RevokeMembership;
use App\Actions\Identity\RevokeRole;
use App\Enums\MembershipRoleScope;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\MembershipInvitation;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: TenantContext} */
function ownerWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => fake()->company()]);

    return [$owner, $tenant, TenantContext::forUser($owner, $tenant->getKey())];
}

it('creates a tenant once and resumes reconciliation through an explicit action', function () {
    $user = User::factory()->create();
    $action = new OnboardTenant;

    $tenant = $action->handle(
        $user,
        ['name' => 'Caldas Centro', 'slug' => 'caldas-centro'],
        ['name' => 'Matriz', 'slug' => 'matriz'],
    );
    expect(fn () => $action->handle(
        ['name' => 'Changed name', 'slug' => 'caldas-centro'],
        $user,
        ['name' => 'Changed unit', 'slug' => 'matriz'],
    ))->toThrow(LogicException::class);

    $again = (new ResumeTenantOnboarding)->handle(
        $user,
        TenantContext::forUser($user, $tenant->getKey()),
        $tenant,
        ['name' => 'Changed unit', 'slug' => 'matriz'],
    );

    expect($again->is($tenant))->toBeTrue()
        ->and(Tenant::query()->where('slug', 'caldas-centro')->count())->toBe(1)
        ->and(Unit::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(Role::query()->where('tenant_id', $tenant->getKey())->where('key', 'owner')->count())->toBe(1)
        ->and(Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->count())->toBe(1)
        ->and(Membership::query()->where('tenant_id', $tenant->getKey())->first()->status)->toBe(MembershipStatus::Active);
});

it('requires a revalidated owner context to resume onboarding', function () {
    [$owner, $tenant] = ownerWorkspace();
    $actor = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $actor->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    $unit = $tenant->units()->firstOrFail();
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $unit->getKey(),
        'is_primary' => true,
    ]);

    expect(fn () => (new ResumeTenantOnboarding)->handle(
        $actor,
        TenantContext::forUser($actor, $tenant->getKey()),
        $tenant,
    ))->toThrow(AuthorizationException::class);
});

it('adds a newly reconciled unit without replacing the existing primary unit', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $originalUnit = $tenant->units()->firstOrFail();

    (new ResumeTenantOnboarding)->handle(
        $owner,
        $context,
        $tenant,
        ['name' => 'Filial Centro', 'slug' => 'filial-centro'],
    );

    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    $newUnit = Unit::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('slug', 'filial-centro')
        ->firstOrFail();

    expect(Unit::query()->where('tenant_id', $tenant->getKey())->count())->toBe(2)
        ->and(MembershipUnit::query()->where('membership_id', $membership->getKey())->where('is_primary', true)->count())->toBe(1)
        ->and(MembershipUnit::query()->where('membership_id', $membership->getKey())->where('unit_id', $originalUnit->getKey())->value('is_primary'))->toBeTrue()
        ->and(MembershipUnit::query()->where('membership_id', $membership->getKey())->where('unit_id', $newUnit->getKey())->value('is_primary'))->toBeFalse();
});

it('keeps memberships and role assignments isolated between tenants', function () {
    [$owner, $tenantA, $context] = ownerWorkspace();
    $tenantB = (new OnboardTenant)->handle($owner, ['name' => 'Tenant B', 'slug' => 'tenant-b']);
    $user = User::factory()->create();
    $membershipA = Membership::factory()->create(['tenant_id' => $tenantA->getKey(), 'user_id' => $user->getKey()]);
    $membershipB = Membership::factory()->create(['tenant_id' => $tenantB->getKey(), 'user_id' => $user->getKey()]);
    $roleA = Role::factory()->create(['tenant_id' => $tenantA->getKey(), 'key' => 'manager']);

    $membershipA->forceFill(['status' => MembershipStatus::Active])->save();
    $assignment = (new AssignRole)->handle($owner, $context, $membershipA, $roleA);

    expect($user->memberships)->toHaveCount(2)
        ->and($membershipA->fresh()->roles)->toHaveCount(1)
        ->and($membershipB->fresh()->roles)->toHaveCount(0)
        ->and($assignment->tenant_id)->toBe($tenantA->getKey());
});

it('requires an active unit or tenant-wide role before activation', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
    ]);

    expect(fn () => (new ActivateMembership)->handle($owner, $context, $membership))
        ->toThrow(LogicException::class);

    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $unit->getKey(),
        'is_primary' => true,
    ]);

    expect((new ActivateMembership)->handle($owner, $context, $membership)->status)->toBe(MembershipStatus::Active);
});

it('rejects activation for an unverified identity', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->unverified()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
    ]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membership->forceFill(['status' => MembershipStatus::Active])->save();
    (new AssignRole)->handle($owner, $context, $membership, $role);
    $membership->forceFill(['status' => MembershipStatus::Invited])->save();

    expect(fn () => (new ActivateMembership)->handle($owner, $context, $membership))
        ->toThrow(LogicException::class);
});

it('revokes role history with membership and allows a clean reinvite', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $assignment = (new AssignRole)->handle($owner, $context, $membership, $role);

    expect((new RevokeMembership)->handle($owner, $context, $membership)->status)->toBe(MembershipStatus::Revoked)
        ->and($assignment->fresh()->revoked_at)->not->toBeNull()
        ->and(MembershipUnit::query()->where('membership_id', $membership->getKey())->count())->toBe(0);

    $reinvited = (new InviteMembership)->handle($owner, $context, $tenant, $user);

    expect($reinvited->status)->toBe(MembershipStatus::Invited)
        ->and(MembershipUnit::query()->where('membership_id', $membership->getKey())->count())->toBe(0)
        ->and(MembershipRole::query()->where('membership_id', $membership->getKey())->count())->toBe(1);

    $reinvited->forceFill(['status' => MembershipStatus::Active])->save();
    $newAssignment = (new AssignRole)->handle($owner, $context, $reinvited, $role);
    (new RevokeRole)->handle($owner, $context, $newAssignment);

    expect(MembershipRole::query()->where('membership_id', $membership->getKey())->count())->toBe(2);
});

it('supports tenant-wide and unit-scoped role assignments without cross-tenant units', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignUnit = Unit::factory()->create(['tenant_id' => $otherTenant->getKey()]);

    $membership->forceFill(['status' => MembershipStatus::Active])->save();

    expect(fn () => (new AssignRole)->handle($owner, $context, $membership, $role, MembershipRoleScope::Unit, $foreignUnit))
        ->toThrow(InvalidArgumentException::class);

    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $assignment = (new AssignRole)->handle($owner, $context, $membership, $role, MembershipRoleScope::Unit, $unit);

    expect($assignment->assignment_scope)->toBe('unit:'.$unit->getKey())
        ->and($assignment->scope_kind)->toBe(MembershipRoleScope::Unit);
});

it('enforces composite tenant foreign keys and one primary unit', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey()]);
    $unitA = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $unitB = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignMembership = Membership::factory()->create(['tenant_id' => $otherTenant->getKey()]);

    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $unitA->getKey(),
        'is_primary' => true,
    ]);

    expect(fn () => MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $foreignMembership->getKey(),
        'unit_id' => $unitB->getKey(),
    ]))->toThrow(QueryException::class)
        ->and(fn () => MembershipUnit::query()->create([
            'tenant_id' => $tenant->getKey(),
            'membership_id' => $membership->getKey(),
            'unit_id' => $unitB->getKey(),
            'is_primary' => true,
        ]))->toThrow(QueryException::class);
});

it('generates UUIDv7 identifiers for tenant aggregates and historical grants', function () {
    $tenant = Tenant::factory()->create();
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);

    expect(Str::isUuid($tenant->getKey(), 7))->toBeTrue()
        ->and(Str::isUuid($role->getKey(), 7))->toBeTrue();
});

it('rejects role assignment to invited memberships and activation from suspended state', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);

    expect(fn () => (new AssignRole)->handle($owner, $context, $membership, $role))
        ->toThrow(LogicException::class);

    $membership->forceFill(['status' => MembershipStatus::Suspended])->save();

    expect(fn () => (new ActivateMembership)->handle($owner, $context, $membership))
        ->toThrow(LogicException::class);
});

it('resolves context only from active membership and attached active units', function () {
    [$owner, $tenant, $context] = ownerWorkspace();

    expect($context->tenant->is($tenant))->toBeTrue()
        ->and($context->unit)->not->toBeNull()
        ->and(TenantContext::forMembership($owner, $context->membership->getKey())->membership->is($context->membership))->toBeTrue();

    $otherTenant = Tenant::factory()->create();

    expect(fn () => TenantContext::forUser($owner, $otherTenant->getKey()))
        ->toThrow(AuthorizationException::class);

    $ownerMembership = $context->membership;
    $ownerMembership->forceFill([
        'status' => MembershipStatus::Revoked,
        'revoked_at' => now(),
    ])->save();

    expect(fn () => TenantContext::forUser($owner, $tenant->getKey()))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => TenantContext::forMembership($owner, $ownerMembership->getKey()))
        ->toThrow(AuthorizationException::class);
});

it('requires the active owner role for sensitive role mutations', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $actor = User::factory()->create();
    $actorMembership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $actor->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $actorMembership->getKey(),
        'unit_id' => $tenant->units()->firstOrFail()->getKey(),
        'is_primary' => true,
    ]);
    $actorContext = TenantContext::forUser($actor, $tenant->getKey());
    $target = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => MembershipStatus::Active,
    ]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'manager']);

    expect(fn () => (new AssignRole)->handle($actor, $actorContext, $target, $role))
        ->toThrow(AuthorizationException::class)
        ->and(Gate::forUser($owner)->allows('update', $role))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', Role::query()->where('key', 'owner')->firstOrFail()))->toBeFalse();

    $ownerAssignment = $context->membership->membershipRoles()->whereNull('revoked_at')->firstOrFail();

    expect(fn () => (new RevokeRole)->handle($owner, $context, $ownerAssignment))
        ->toThrow(LogicException::class);
});

it('protects system roles from direct update and delete mutations', function () {
    [$owner, $tenant] = ownerWorkspace();
    $ownerRole = $tenant->roles()->where('key', 'owner')->firstOrFail();

    expect(fn () => Role::query()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'forged-system-role',
        'name' => 'Forged system role',
        'is_system' => true,
    ]))->toThrow(LogicException::class)
        ->and(fn () => $ownerRole->update(['name' => 'Compromised']))
        ->toThrow(LogicException::class)
        ->and(fn () => $ownerRole->delete())
        ->toThrow(LogicException::class);
});

it('builds composite pivot factories without database writes in definitions', function () {
    $tenant = Tenant::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey()]);
    $unit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membershipUnit = MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $membershipRole = MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    expect($membershipUnit->tenant_id)->toBe($membershipUnit->membership->tenant_id)
        ->and($membershipUnit->tenant_id)->toBe($membershipUnit->unit->tenant_id)
        ->and($membershipRole->tenant_id)->toBe($membershipRole->membership->tenant_id)
        ->and($membershipRole->tenant_id)->toBe($membershipRole->role->tenant_id);
});

it('builds a branded invitation notification for an existing collaborator', function () {
    $user = User::factory()->create(['name' => 'Ana']);
    $notification = new MembershipInvitation('Caldas Centro', route('login'));
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Você recebeu um convite para Caldas Centro')
        ->and($mail->greeting)->toBe('Olá, Ana!')
        ->and($mail->actionText)->toBe('Acessar o sistema')
        ->and($notification->afterCommit)->toBeTrue();
});

it('sends invitations only when membership is created or reinvited', function () {
    [$owner, $tenant, $context] = ownerWorkspace();
    $user = User::factory()->create();
    Notification::fake();

    $membership = (new InviteMembership)->handle($owner, $context, $tenant, $user);

    Notification::assertSentToTimes($user, MembershipInvitation::class, 1);

    (new InviteMembership)->handle($owner, $context, $tenant, $user);
    Notification::assertSentToTimes($user, MembershipInvitation::class, 1);

    $membership->forceFill(['status' => MembershipStatus::Active])->save();
    (new InviteMembership)->handle($owner, $context, $tenant, $user);
    Notification::assertSentToTimes($user, MembershipInvitation::class, 1);

    (new RevokeMembership)->handle($owner, $context, $membership);
    (new InviteMembership)->handle($owner, $context, $tenant, $user);

    Notification::assertSentToTimes($user, MembershipInvitation::class, 2);
});
