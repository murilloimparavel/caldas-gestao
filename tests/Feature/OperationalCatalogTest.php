<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function operationalWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates, updates, searches and inactivates the customer catalog', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();

    $createResponse = $this->actingAs($owner)->post(route('customers.store'), [
        'name' => 'Ana Cliente',
        'email' => null,
        'phone' => '+55 11 99999-1234',
        'notes' => 'Prefere atendimento no período da manhã.',
    ]);
    expect($createResponse->status())->toBe(302);

    $customer = Customer::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $createResponse->assertRedirect(route('customers.show', $customer));
    expect($customer->unit_id)->toBe($unit->getKey())
        ->and($customer->status)->toBe('active')
        ->and(AuditEvent::query()->where('action', 'customer.created')->where('resource_id', $customer->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('customers.index', ['search' => 'Ana']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('filters.search', 'Ana')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $customer->getKey()));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('customers.update', $customer), [
            'name' => 'Ana Atualizada',
            'email' => null,
            'phone' => $customer->phone,
            'lock_version' => 0,
        ])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->fresh()->name)->toBe('Ana Atualizada')
        ->and($customer->fresh()->lock_version)->toBe(1);

    $this->actingAs($owner)
        ->delete(route('customers.destroy', $customer), ['lock_version' => 1])
        ->assertRedirect(route('customers.index'));

    expect($customer->fresh()->status)->toBe('inactive')
        ->and($customer->fresh()->lock_version)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'customer.deactivated')->where('resource_id', $customer->getKey())->exists())->toBeTrue();
});

it('creates professionals and services with a tenant-unit scoped relationship', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();

    $serviceResponse = $this->actingAs($owner)->post(route('services.store'), [
        'name' => 'Corte feminino',
        'description' => 'Corte e finalização.',
        'duration_minutes' => 75,
        'price_cents' => 12500,
    ]);
    expect($serviceResponse->status())->toBe(302);
    $service = Service::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $serviceResponse->assertRedirect(route('services.show', $service));

    $this->actingAs($owner)
        ->get(route('services.show', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/show')
            ->where('professionalOptions', []),
        );

    $professionalResponse = $this->actingAs($owner)->post(route('professionals.store'), [
        'name' => 'Beatriz Profissional',
        'email' => null,
        'service_ids' => [$service->getKey()],
    ]);
    $professional = Professional::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $professionalResponse->assertRedirect(route('professionals.show', $professional));
    expect($service->unit_id)->toBe($unit->getKey())
        ->and($professional->unit_id)->toBe($unit->getKey())
        ->and($professional->services()->whereKey($service->getKey())->exists())->toBeTrue()
        ->and($professional->services()->first()->pivot->tenant_id)->toBe($tenant->getKey())
        ->and(AuditEvent::query()->where('action', 'professional.created')->where('resource_id', $professional->getKey())->firstOrFail()->metadata['service_ids'])->toBe([$service->getKey()])
        ->and(OutboxEvent::query()->where('event_type', 'professional.created')->where('aggregate_id', $professional->getKey())->firstOrFail()->payload['service_ids'])->toBe([$service->getKey()]);

    $this->actingAs($owner)
        ->get(route('services.show', $service))
        ->assertInertia(fn (Assert $page) => $page
            ->where('professionalOptions.0.id', $professional->getKey())
            ->where('professionalOptions.0.name', $professional->name),
        );

    $this->actingAs($owner)
        ->patch(route('services.update', $service), [
            'name' => 'Corte feminino premium',
            'duration_minutes' => 90,
            'price_cents' => 15000,
            'professional_ids' => [$professional->getKey()],
            'lock_version' => 0,
        ])
        ->assertRedirect(route('services.show', $service));

    expect($service->fresh()->name)->toBe('Corte feminino premium')
        ->and($service->fresh()->price_cents)->toBe(15000)
        ->and($service->fresh()->professionals()->whereKey($professional->getKey())->exists())->toBeTrue();
});

it('supports professional inactivation without requiring a full delete payload', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $this->actingAs($owner)
        ->delete(route('professionals.destroy', $professional), ['lock_version' => 0])
        ->assertRedirect(route('professionals.index'));

    expect($professional->fresh()->status)->toBe('inactive')
        ->and(AuditEvent::query()->where('action', 'professional.deactivated')->where('resource_id', $professional->getKey())->exists())->toBeTrue();
});

it('supports service inactivation and preserves the service snapshot source fields', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'duration_minutes' => 60,
        'price_cents' => 10000,
    ]);

    $this->actingAs($owner)
        ->delete(route('services.destroy', $service), ['lock_version' => 0])
        ->assertRedirect(route('services.index'));

    expect($service->fresh()->status)->toBe('inactive')
        ->and($service->fresh()->duration_minutes)->toBe(60)
        ->and($service->fresh()->price_cents)->toBe(10000)
        ->and(AuditEvent::query()->where('action', 'service.deactivated')->where('resource_id', $service->getKey())->exists())->toBeTrue();
});

it('rejects records from another tenant and another unit', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignCustomer = Customer::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $foreignTenant->getKey())
        ->get(route('customers.index'))
        ->assertForbidden();

    $secondUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $otherUnitCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $secondUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $tenant->getKey())
        ->withHeader('X-Unit-Id', $unit->getKey())
        ->get(route('customers.show', $otherUnitCustomer))
        ->assertForbidden();

    expect($foreignCustomer->fresh()->status)->toBe('active');
});

it('enforces catalog policies for a member with read-only access', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'catalog-reader']);
    $permission = Permission::query()->where('key', 'customer.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page->component('customers/index'));

    $this->actingAs($reader)
        ->post(route('customers.store'), ['name' => 'Blocked Write'])
        ->assertForbidden();

    expect(TenantContext::forUser($reader, $tenant->getKey(), $unit->getKey())->tenant->is($tenant))
        ->toBeTrue()
        ->and($owner->fresh()->is($owner))->toBeTrue();
});

it('replays an idempotent customer mutation and protects optimistic versions', function () {
    [$owner, $tenant] = operationalWorkspace();
    $payload = ['name' => 'Idempotent Customer', 'phone' => '+55 11 98888-0000'];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'customer-create-1')
        ->post(route('customers.store'), $payload);
    $customer = Customer::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'customer-create-1')
        ->post(route('customers.store'), $payload);

    $first->assertRedirect(route('customers.show', $customer));
    $second->assertRedirect(route('customers.show', $customer));
    expect(Customer::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'customer-create-1')->firstOrFail()->status->value)->toBe('succeeded');

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('customers.update', $customer), [
            'name' => 'Versioned Customer',
            'lock_version' => 0,
        ])
        ->assertRedirect(route('customers.show', $customer));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('customers.update', $customer), [
            'name' => 'Stale Customer',
            'lock_version' => 0,
        ])
        ->assertStatus(409);

    expect($customer->fresh()->name)->toBe('Versioned Customer')
        ->and($customer->fresh()->lock_version)->toBe(1);
});

it('rejects stale or missing versions with a conflict-safe response', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $this->actingAs($owner)
        ->patchJson(route('customers.update', $customer), [
            'name' => 'Missing Version',
        ])
        ->assertUnprocessable();

    $this->actingAs($owner)
        ->deleteJson(route('customers.destroy', $customer))
        ->assertUnprocessable();

    expect($customer->fresh()->name)->not->toBe('Missing Version')
        ->and($customer->fresh()->lock_version)->toBe(0);
});

it('rejects relationship ids that belong to another unit during validation', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $secondUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membership = $tenant->memberships()->where('user_id', $owner->getKey())->firstOrFail();
    MembershipUnit::factory()->forMembership($membership)->forUnit($secondUnit)->create();
    $foreignService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $secondUnit->getKey(),
    ]);
    $foreignProfessional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $secondUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('professionals.store'), [
            'name' => 'Invalid Service Link',
            'service_ids' => [$foreignService->getKey()],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service_ids.0']);

    $this->actingAs($owner)
        ->postJson(route('services.store'), [
            'name' => 'Invalid Professional Link',
            'duration_minutes' => 60,
            'price_cents' => 10000,
            'professional_ids' => [$foreignProfessional->getKey()],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['professional_ids.0']);

    expect(Professional::query()->where('name', 'Invalid Service Link')->exists())->toBeFalse()
        ->and(Service::query()->where('name', 'Invalid Professional Link')->exists())->toBeFalse();
});

it('does not replay an idempotency key across resources or units', function () {
    [$owner, $tenant, $unit] = operationalWorkspace();
    $firstCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $secondCustomer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $payload = ['name' => 'Scoped Customer', 'lock_version' => 0];

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'scoped-mutation')
        ->patch(route('customers.update', $firstCustomer), $payload)
        ->assertRedirect(route('customers.show', $firstCustomer));

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'scoped-mutation')
        ->patch(route('customers.update', $secondCustomer), $payload)
        ->assertStatus(409);

    $secondUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membership = $tenant->memberships()->where('user_id', $owner->getKey())->firstOrFail();
    MembershipUnit::factory()->forMembership($membership)->forUnit($secondUnit)->create();

    $this->actingAs($owner)
        ->withHeader('X-Tenant-Id', $tenant->getKey())
        ->withHeader('X-Unit-Id', $secondUnit->getKey())
        ->withHeader('X-Idempotency-Key', 'scoped-mutation')
        ->post(route('customers.store'), ['name' => 'Scoped Customer'])
        ->assertStatus(409);

    expect($secondCustomer->fresh()->name)->not->toBe('Scoped Customer')
        ->and(Customer::query()->where('unit_id', $secondUnit->getKey())->count())->toBe(0);
});

it('reconciles new owner permissions for an existing tenant', function () {
    [$owner, $tenant] = operationalWorkspace();
    $ownerRole = $tenant->roles()->where('key', 'owner')->firstOrFail();
    $permission = Permission::query()->where('key', 'service.manage')->firstOrFail();

    RolePermission::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('role_id', $ownerRole->getKey())
        ->where('permission_id', $permission->getKey())
        ->delete();

    $this->artisan('app:reconcile-owner-permissions', ['--tenant' => $tenant->getKey()])
        ->assertSuccessful();

    expect(RolePermission::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('role_id', $ownerRole->getKey())
        ->where('permission_id', $permission->getKey())
        ->exists())->toBeTrue();
});

it('creates default operational factories within one tenant and unit', function () {
    $customer = Customer::factory()->create();
    $professional = Professional::factory()->create();
    $service = Service::factory()->create();

    expect($customer->tenant_id)->toBe($customer->unit->tenant_id)
        ->and($professional->tenant_id)->toBe($professional->unit->tenant_id)
        ->and($service->tenant_id)->toBe($service->unit->tenant_id);
});
