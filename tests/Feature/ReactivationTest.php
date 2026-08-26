<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Professional;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function reactivationWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('filters professionals by status and reactivates an inactive professional', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
        'lock_version' => 0,
    ]);

    $inactive = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'inactive',
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('professionals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/index')
            ->where('filters.status', 'active')
            ->has('professionals.data', 1)
            ->where('professionals.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('professionals.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/index')
            ->where('filters.status', 'inactive')
            ->has('professionals.data', 1)
            ->where('professionals.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('professionals.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('professionals/index')
            ->where('filters.status', 'all')
            ->has('professionals.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('professionals.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('professionals.show', $inactive));

    expect($inactive->fresh()->status)->toBe('active')
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'professional.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters customers by status and reactivates an inactive customer', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
        'lock_version' => 0,
    ]);

    $inactive = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'inactive',
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('filters.status', 'active')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('customers.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('filters.status', 'inactive')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('customers.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('filters.status', 'all')
            ->has('customers.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('customers.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('customers.show', $inactive));

    expect($inactive->fresh()->status)->toBe('active')
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'customer.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters products by status and reactivates an inactive product', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
        'lock_version' => 0,
    ]);

    $inactive = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => false,
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->where('filters.status', 'active')
            ->has('products.data', 1)
            ->where('products.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('products.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->where('filters.status', 'inactive')
            ->has('products.data', 1)
            ->where('products.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('products.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->where('filters.status', 'all')
            ->has('products.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('products.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('products.show', $inactive));

    expect($inactive->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'product.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters services by status and reactivates an inactive service', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
        'lock_version' => 0,
    ]);

    $inactive = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'inactive',
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('services.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/index')
            ->where('filters.status', 'active')
            ->has('services.data', 1)
            ->where('services.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('services.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/index')
            ->where('filters.status', 'inactive')
            ->has('services.data', 1)
            ->where('services.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('services.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/index')
            ->where('filters.status', 'all')
            ->has('services.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('services.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('services.show', $inactive));

    expect($inactive->fresh()->status)->toBe('active')
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'service.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters suppliers by status and reactivates an inactive supplier', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Supplier::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
        'lock_version' => 0,
    ]);

    $inactive = Supplier::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => false,
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('suppliers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('suppliers/index')
            ->where('filters.status', 'active')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('suppliers.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('suppliers/index')
            ->where('filters.status', 'inactive')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('suppliers.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('suppliers/index')
            ->where('filters.status', 'all')
            ->has('suppliers.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('suppliers.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('suppliers.show', $inactive));

    expect($inactive->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'supplier.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters categories by status and reactivates an inactive category', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
        'lock_version' => 0,
    ]);

    $inactive = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => false,
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->where('filters.status', 'active')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('categories.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->where('filters.status', 'inactive')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('categories.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->where('filters.status', 'all')
            ->has('categories.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('categories.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('categories.show', $inactive));

    expect($inactive->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'category.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});

it('filters sale-categories by status and reactivates an inactive sale-category', function () {
    [$owner, $tenant, $unit] = reactivationWorkspace();

    $active = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
        'lock_version' => 0,
    ]);

    $inactive = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => false,
        'lock_version' => 0,
    ]);

    // Active (default)
    $this->actingAs($owner)
        ->get(route('sale-categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sale-categories/index')
            ->where('filters.status', 'active')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $active->getKey()));

    // Inactive
    $this->actingAs($owner)
        ->get(route('sale-categories.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sale-categories/index')
            ->where('filters.status', 'inactive')
            ->has('categories.data', 1)
            ->where('categories.data.0.id', $inactive->getKey()));

    // All
    $this->actingAs($owner)
        ->get(route('sale-categories.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sale-categories/index')
            ->where('filters.status', 'all')
            ->has('categories.data', 2));

    // Reactivate inactive
    $this->actingAs($owner)
        ->patch(route('sale-categories.reactivate', $inactive), ['lock_version' => 0])
        ->assertRedirect(route('sale-categories.show', $inactive));

    expect($inactive->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->lock_version)->toBe(1)
        ->and(AuditEvent::query()->where('action', 'sale_category.reactivated')->where('resource_id', $inactive->getKey())->exists())->toBeTrue();
});
