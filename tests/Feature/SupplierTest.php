<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function supplierTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates, updates, searches and deactivates suppliers', function () {
    [$owner, $tenant, $unit] = supplierTestWorkspace();

    $createResponse = $this->actingAs($owner)->post(route('suppliers.store'), [
        'name' => 'Distribuidora Alpha',
        'trade_name' => 'Alpha Cosméticos',
        'document_number' => '12.345.678/0001-90',
        'email' => 'contato@alpha.com.br',
        'phone' => '(11) 98888-7777',
        'notes' => 'Fornecedor principal de pomadas e shampoos',
        'is_active' => true,
    ]);
    expect($createResponse->status())->toBe(302);

    $supplier = Supplier::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $createResponse->assertRedirect(route('suppliers.show', $supplier));
    expect($supplier->unit_id)->toBe($unit->getKey())
        ->and($supplier->name)->toBe('Distribuidora Alpha')
        ->and($supplier->trade_name)->toBe('Alpha Cosméticos')
        ->and($supplier->document_number)->toBe('12.345.678/0001-90')
        ->and($supplier->email)->toBe('contato@alpha.com.br')
        ->and($supplier->phone)->toBe('(11) 98888-7777')
        ->and($supplier->notes)->toBe('Fornecedor principal de pomadas e shampoos')
        ->and($supplier->is_active)->toBeTrue()
        ->and($supplier->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'supplier.created')->where('resource_id', $supplier->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('suppliers.index', ['search' => 'Alpha']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('suppliers/index')
            ->where('filters.search', 'Alpha')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.id', $supplier->getKey()));

    $this->flushHeaders()->actingAs($owner)
        ->patch(route('suppliers.update', $supplier), [
            'name' => 'Distribuidora Alpha Matriz',
            'trade_name' => 'Alpha Cosméticos & Barba',
            'document_number' => '12.345.678/0001-90',
            'email' => 'financeiro@alpha.com.br',
            'phone' => '(11) 99999-8888',
            'notes' => 'Condições especiais no boleto 30 dias',
            'lock_version' => 1,
        ])
        ->assertRedirect(route('suppliers.show', $supplier));

    expect($supplier->fresh()->name)->toBe('Distribuidora Alpha Matriz')
        ->and($supplier->fresh()->trade_name)->toBe('Alpha Cosméticos & Barba')
        ->and($supplier->fresh()->email)->toBe('financeiro@alpha.com.br')
        ->and($supplier->fresh()->lock_version)->toBe(2)
        ->and(AuditEvent::query()->where('action', 'supplier.updated')->where('resource_id', $supplier->getKey())->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('suppliers.destroy', $supplier), ['lock_version' => 2])
        ->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->is_active)->toBeFalse()
        ->and($supplier->fresh()->lock_version)->toBe(3)
        ->and(AuditEvent::query()->where('action', 'supplier.deactivated')->where('resource_id', $supplier->getKey())->exists())->toBeTrue();
});

it('rejects stale lock_version when updating suppliers', function () {
    [$owner, $tenant, $unit] = supplierTestWorkspace();
    $supplier = Supplier::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $this->actingAs($owner)
        ->patch(route('suppliers.update', $supplier), [
            'name' => 'Conflito Fornecedor',
            'lock_version' => 999,
        ])
        ->assertStatus(409);

    expect($supplier->fresh()->name)->not->toBe('Conflito Fornecedor')
        ->and($supplier->fresh()->lock_version)->toBe(1);
});

it('enforces RBAC permissions on suppliers', function () {
    [$owner, $tenant, $unit] = supplierTestWorkspace();
    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'supplier-reader']);
    $permission = Permission::query()->where('key', 'supplier.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->get(route('suppliers.index'))
        ->assertInertia(fn (Assert $page) => $page->component('suppliers/index'));

    $this->actingAs($reader)
        ->post(route('suppliers.store'), [
            'name' => 'Blocked Supplier',
        ])
        ->assertForbidden();
});

it('isolates suppliers across foreign tenants and units', function () {
    [$owner, $tenant, $unit] = supplierTestWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignSupplier = Supplier::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('suppliers.show', $foreignSupplier))
        ->assertForbidden();
});

it('loads recent appointments when viewing customer details', function () {
    [$owner, $tenant, $unit] = supplierTestWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);

    $this->actingAs($owner)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/show')
            ->has('customer.appointments', 1)
            ->where('customer.appointments.0.id', $appointment->getKey())
            ->where('customer.appointments.0.professional.id', $professional->getKey()));
});
