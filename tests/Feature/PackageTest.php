<?php

use App\Actions\Finance\Cash\OpenCashShift;
use App\Actions\Identity\OnboardTenant;
use App\Models\CashMovement;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\PackageTemplate;
use App\Models\Permission;
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
function packageTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('allows creating, updating, viewing, and deactivating package templates', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $service1 = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Masculino',
        'price_cents' => 6000,
    ]);

    $service2 = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barba Completa',
        'price_cents' => 4500,
    ]);

    $response = $this->actingAs($owner)->post(route('packages.store'), [
        'name' => 'Combo Cabelo & Barba 5x',
        'description' => 'Pacote com 5 sessões de corte e barba',
        'price_cents' => 45000,
        'total_sessions' => 5,
        'validity_days' => 90,
        'service_ids' => [$service1->getKey(), $service2->getKey()],
    ]);

    $response->assertSessionHasNoErrors();
    $template = PackageTemplate::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('packages.show', $template));

    expect($template->name)->toBe('Combo Cabelo & Barba 5x')
        ->and($template->price_cents)->toBe(45000)
        ->and($template->total_sessions)->toBe(5)
        ->and($template->validity_days)->toBe(90)
        ->and($template->is_active)->toBeTrue()
        ->and($template->services)->toHaveCount(2);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'package_template.created',
    ]);

    // Index listing
    $indexResponse = $this->actingAs($owner)->get(route('packages.index'));
    $indexResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('packages/index')
            ->has('packages.data', 1)
            ->where('packages.data.0.name', 'Combo Cabelo & Barba 5x')
        );

    // Show view
    $showResponse = $this->actingAs($owner)->get(route('packages.show', $template));
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('packages/show')
            ->where('package.name', 'Combo Cabelo & Barba 5x')
            ->has('package.services', 2)
        );

    // Update
    $updateResponse = $this->actingAs($owner)->put(route('packages.update', $template), [
        'name' => 'Combo Cabelo & Barba 5x VIP',
        'description' => 'Pacote VIP atualizado',
        'price_cents' => 48000,
        'total_sessions' => 5,
        'validity_days' => 120,
        'service_ids' => [$service1->getKey()],
        'lock_version' => $template->lock_version,
    ]);

    $updateResponse->assertSessionHasNoErrors();
    $template->refresh();
    expect($template->name)->toBe('Combo Cabelo & Barba 5x VIP')
        ->and($template->price_cents)->toBe(48000)
        ->and($template->validity_days)->toBe(120)
        ->and($template->services)->toHaveCount(1);

    // Deactivate
    $destroyResponse = $this->actingAs($owner)->delete(route('packages.destroy', $template), [
        'lock_version' => $template->lock_version,
    ]);
    $destroyResponse->assertSessionHasNoErrors();
    $template->refresh();
    expect($template->is_active)->toBeFalse();

    // Reactivate
    $reactivateResponse = $this->actingAs($owner)->patch(route('packages.reactivate', $template), [
        'lock_version' => $template->lock_version,
    ]);
    $reactivateResponse->assertSessionHasNoErrors();
    $template->refresh();
    expect($template->is_active)->toBeTrue();
});

it('prevents optimistic concurrency conflicts on package template update', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->put(route('packages.update', $template), [
        'name' => 'Novo Nome',
        'price_cents' => 20000,
        'total_sessions' => 3,
        'validity_days' => 60,
        'lock_version' => 0, // Stale version
    ]);

    $response->assertStatus(409);
});

it('sells a package to a customer and calculates validity', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 10,
        'validity_days' => 60,
        'is_active' => true,
    ]);

    $response = $this->actingAs($owner)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'payment_method' => 'pix',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $customerPackage = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();

    expect($customerPackage->total_sessions)->toBe(10)
        ->and($customerPackage->remaining_sessions)->toBe(10)
        ->and($customerPackage->status)->toBe('active')
        ->and($customerPackage->expires_at?->toDateString())->toBe(now()->addDays(60)->toDateString())
        ->and($customerPackage->financialObligation->status)->toBe('paid')
        ->and($customerPackage->financialObligation->amount_cents)->toBe($template->price_cents)
        ->and($customerPackage->financialObligation->payment_method)->toBe('pix');

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->getKey(),
        'action' => 'customer_package.sold',
    ]);
});

it('records one paid financial obligation and one cash movement for an idempotent package purchase', function () {
    [$owner, $tenant, $unit, $context] = packageTestWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'price_cents' => 27500,
    ]);
    $shift = (new OpenCashShift)->handle($owner, $context, ['initial_amount_cents' => 10000]);
    $payload = [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'payment_method' => 'dinheiro',
    ];

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-purchase-once')
        ->post(route('customer-packages.store'), $payload)
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-purchase-once')
        ->post(route('customer-packages.store'), $payload)
        ->assertSessionHasNoErrors();

    $customerPackage = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    $obligation = FinancialObligation::query()->where('customer_package_id', $customerPackage->getKey())->firstOrFail();
    $movement = CashMovement::query()
        ->where('reference_type', 'financial_obligation')
        ->where('reference_id', $obligation->getKey())
        ->firstOrFail();

    expect(CustomerPackage::query()->where('customer_id', $customer->getKey())->count())->toBe(1)
        ->and(FinancialObligation::query()->where('customer_package_id', $customerPackage->getKey())->count())->toBe(1)
        ->and($obligation->type)->toBe('receivable')
        ->and($obligation->status)->toBe('paid')
        ->and($obligation->amount_cents)->toBe(27500)
        ->and($obligation->payment_method)->toBe('dinheiro')
        ->and($movement->cash_shift_id)->toBe($shift->getKey())
        ->and($movement->type)->toBe('supply')
        ->and($movement->amount_cents)->toBe(27500)
        ->and($shift->fresh()->expected_amount_cents)->toBe(37500);
});

it('requires finance permissions before a package purchase can create a payment record', function () {
    [, $tenant, $unit] = packageTestWorkspace();
    $staff = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $staff->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Package seller']);
    $packageSellPermission = Permission::query()->where('key', 'package.sell')->firstOrFail();
    RolePermission::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $packageSellPermission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $this->actingAs($staff)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'payment_method' => 'pix',
    ])->assertForbidden();

    expect(CustomerPackage::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse()
        ->and(FinancialObligation::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse();
});

it('consumes package sessions atomically, records usage and exhausts package on 0 remaining', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 2,
    ]);

    $customerPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'total_sessions' => 2,
        'remaining_sessions' => 2,
        'status' => 'active',
        'expires_at' => now()->addDays(30)->toDateString(),
    ]);

    // 1st consumption
    $consume1 = $this->actingAs($owner)->post(route('customer-packages.consume', $customerPackage), [
        'sessions_consumed' => 1,
    ]);
    $consume1->assertSessionHasNoErrors();

    $customerPackage->refresh();
    expect($customerPackage->remaining_sessions)->toBe(1)
        ->and($customerPackage->status)->toBe('active');

    $this->assertDatabaseHas('package_usages', [
        'customer_package_id' => $customerPackage->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    // 2nd consumption (exhausts package)
    $consume2 = $this->actingAs($owner)->post(route('customer-packages.consume', $customerPackage), [
        'sessions_consumed' => 1,
    ]);
    $consume2->assertSessionHasNoErrors();

    $customerPackage->refresh();
    expect($customerPackage->remaining_sessions)->toBe(0)
        ->and($customerPackage->status)->toBe('exhausted');

    // 3rd consumption attempt should fail with 409
    $consume3 = $this->actingAs($owner)->post(route('customer-packages.consume', $customerPackage), [
        'sessions_consumed' => 1,
    ]);
    $consume3->assertStatus(409);
});

it('fails to consume from an expired package and transitions its status to expired', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $customerPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 5,
        'remaining_sessions' => 5,
        'status' => 'active',
        'expires_at' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($owner)->post(route('customer-packages.consume', $customerPackage), [
        'sessions_consumed' => 1,
    ]);

    $response->assertStatus(409);

    $customerPackage->refresh();
    expect($customerPackage->status)->toBe('expired');
});

it('enforces RBAC permissions for package operations', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();

    $staff = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $staff->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'membership_id' => $membership->getKey(),
    ]);

    $viewOnlyRole = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Package Viewer',
        'key' => 'package-viewer',
    ]);
    $viewPerm = Permission::query()->where('key', 'package.view')->firstOrFail();
    RolePermission::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'role_id' => $viewOnlyRole->getKey(),
        'permission_id' => $viewPerm->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($viewOnlyRole)->create();

    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    // View allowed
    $this->actingAs($staff)->get(route('packages.index'))->assertOk();
    $this->actingAs($staff)->get(route('packages.show', $template))->assertOk();

    // Create denied (403)
    $this->actingAs($staff)->post(route('packages.store'), [
        'name' => 'Tentativa Não Autorizada',
        'price_cents' => 10000,
        'total_sessions' => 2,
        'validity_days' => 30,
    ])->assertForbidden();
});

it('isolates packages across foreign tenants and units', function () {
    [$owner, $tenant, $unit] = packageTestWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignTemplate = PackageTemplate::factory()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('packages.show', $foreignTemplate))
        ->assertForbidden();
});
