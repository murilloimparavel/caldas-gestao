<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\InventoryMovement;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function inventoryTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('records purchase inflow and increases product stock', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 10,
        'cost_price_cents' => 2000,
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('inventory.movements.store'), [
        'product_id' => $product->getKey(),
        'type' => 'purchase_inflow',
        'quantity' => 15,
        'unit_cost_cents' => 2200,
        'reason' => 'Entrada NF 1042',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $product->refresh();
    expect($product->current_stock)->toBe(25)
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('purchase_inflow')
        ->and($movement->quantity)->toBe(15)
        ->and($movement->unit_cost_cents)->toBe(2200)
        ->and($movement->previous_stock)->toBe(10)
        ->and($movement->resulting_stock)->toBe(25)
        ->and($movement->reason)->toBe('Entrada NF 1042')
        ->and($movement->user_id)->toBe($owner->getKey());

    expect(AuditEvent::query()->where('action', 'inventory.moved')->where('resource_id', $product->getKey())->exists())->toBeTrue();
});

it('records adjustment loss and decreases product stock', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 20,
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('inventory.movements.store'), [
        'product_id' => $product->getKey(),
        'type' => 'adjustment_loss',
        'quantity' => 3,
        'reason' => 'Frasco danificado durante manuseio',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->current_stock)->toBe(17)
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('adjustment_loss')
        ->and($movement->quantity)->toBe(3)
        ->and($movement->previous_stock)->toBe(20)
        ->and($movement->resulting_stock)->toBe(17);
});

it('records adjustment gain and increases product stock', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 5,
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('inventory.movements.store'), [
        'product_id' => $product->getKey(),
        'type' => 'adjustment_gain',
        'quantity' => 2,
        'reason' => 'Sobra identificada em conferência',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->current_stock)->toBe(7)
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('adjustment_gain')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->previous_stock)->toBe(5)
        ->and($movement->resulting_stock)->toBe(7);
});

it('records manual physical count and sets product stock directly', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 12,
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('inventory.movements.store'), [
        'product_id' => $product->getKey(),
        'type' => 'manual_count',
        'quantity' => 8,
        'reason' => 'Balanço mensal de inventário',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->current_stock)->toBe(8)
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('manual_count')
        ->and($movement->quantity)->toBe(8)
        ->and($movement->previous_stock)->toBe(12)
        ->and($movement->resulting_stock)->toBe(8);
});

it('automatically creates sale_outflow inventory movement on FinalizeClosingSession for product items', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Geral',
        'key' => 'geral',
        'type' => 'mixed',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cliente Teste',
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Pomada Modeladora',
        'current_stock' => 50,
        'cost_price_cents' => 2000,
        'sale_price_cents' => 5000,
        'lock_version' => 1,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 10000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 10000,
        'lock_version' => 1,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'item_type' => 'product',
        'name_snapshot' => 'Pomada Modeladora',
        'unit_price_cents' => 5000,
        'quantity' => 2,
        'discount_cents' => 0,
        'total_cents' => 10000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 10000,
        'payment_method' => 'pix',
    ]);

    $response->assertSessionHasNoErrors();
    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    $product->refresh();
    expect($product->current_stock)->toBe(48)
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('sale_outflow')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->previous_stock)->toBe(50)
        ->and($movement->resulting_stock)->toBe(48)
        ->and($movement->reference_type)->toBe('closing_session')
        ->and($movement->reference_id)->toBe($session->getKey());
});

it('rejects inventory adjustment with outdated lock_version (optimistic concurrency conflict)', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 10,
        'lock_version' => 2,
    ]);

    $this->actingAs($owner)
        ->post(route('inventory.movements.store'), [
            'product_id' => $product->getKey(),
            'type' => 'purchase_inflow',
            'quantity' => 5,
            'reason' => 'Entrada concorrente',
            'lock_version' => 1, // outdated
        ])
        ->assertStatus(409);

    expect($product->fresh()->current_stock)->toBe(10)
        ->and($product->fresh()->lock_version)->toBe(2);
});

it('enforces tenant and unit isolation for inventory movements', function () {
    [$ownerA, $tenantA, $unitA] = inventoryTestWorkspace();
    [$ownerB, $tenantB, $unitB] = inventoryTestWorkspace();

    $foreignProduct = Product::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'unit_id' => $unitB->getKey(),
        'current_stock' => 10,
    ]);

    $this->actingAs($ownerA)
        ->post(route('inventory.movements.store'), [
            'product_id' => $foreignProduct->getKey(),
            'type' => 'purchase_inflow',
            'quantity' => 5,
            'reason' => 'Tentativa cross-tenant',
        ])
        ->assertNotFound();

    expect($foreignProduct->fresh()->current_stock)->toBe(10);
});

it('enforces RBAC permissions on inventory movements', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 10,
    ]);

    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'inventory-reader']);
    $permission = Permission::query()->where('key', 'inventory.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    // Reader can view inventory index
    $this->actingAs($reader)
        ->get(route('inventory.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inventory/index'));

    // Reader cannot store movement
    $this->actingAs($reader)
        ->post(route('inventory.movements.store'), [
            'product_id' => $product->getKey(),
            'type' => 'purchase_inflow',
            'quantity' => 5,
            'reason' => 'Tentativa sem permissão',
        ])
        ->assertForbidden();
});

it('lists inventory movements via inventory index', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Gel Fixador',
    ]);

    InventoryMovement::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'product_id' => $product->getKey(),
        'type' => 'purchase_inflow',
        'quantity' => 20,
        'user_id' => $owner->getKey(),
    ]);

    $this->actingAs($owner)
        ->get(route('inventory.index', ['product_id' => $product->getKey()]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/index')
            ->has('movements.data', 1)
            ->where('movements.data.0.quantity', 20)
        );
});

it('summarizes current inventory value by category independently from movement filters', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();
    $clothing = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Roupas',
        'type' => 'product',
    ]);
    $perfume = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Perfumes',
        'type' => 'product',
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $clothing->getKey(),
        'current_stock' => 2,
        'cost_price_cents' => 100,
        'sale_price_cents' => 250,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $clothing->getKey(),
        'current_stock' => 3,
        'cost_price_cents' => 300,
        'sale_price_cents' => 500,
        'is_active' => false,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $perfume->getKey(),
        'current_stock' => 4,
        'cost_price_cents' => 250,
        'sale_price_cents' => 600,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 1,
        'cost_price_cents' => 1000,
        'sale_price_cents' => 2000,
    ]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 0,
        'cost_price_cents' => 9999,
        'sale_price_cents' => 9999,
    ]);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'current_stock' => 100,
        'cost_price_cents' => 9999,
        'sale_price_cents' => 9999,
    ]);

    $this->actingAs($owner)
        ->get(route('inventory.index', ['type' => 'purchase_inflow']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/index')
            ->missing('inventorySummary.total_units')
            ->where('inventorySummary.total_cost_cents', 3100)
            ->where('inventorySummary.total_sale_cents', 6400)
            ->has('inventorySummary.categories', 3)
            ->missing('inventorySummary.categories.0.units')
            ->where('inventorySummary.categories.0.name', 'Perfumes')
            ->where('inventorySummary.categories.0.cost_value_cents', 1000)
            ->where('inventorySummary.categories.0.sale_value_cents', 2400)
            ->where('inventorySummary.categories.1.name', 'Roupas')
            ->where('inventorySummary.categories.1.cost_value_cents', 1100)
            ->where('inventorySummary.categories.1.sale_value_cents', 2000)
            ->where('inventorySummary.categories.2.name', 'Sem categoria')
            ->where('inventorySummary.categories.2.cost_value_cents', 1000)
            ->where('inventorySummary.categories.2.sale_value_cents', 2000));
});

it('replays an idempotent inventory movement mutation', function () {
    [$owner, $tenant, $unit] = inventoryTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'current_stock' => 10,
        'lock_version' => 1,
    ]);

    $payload = [
        'product_id' => $product->getKey(),
        'type' => 'purchase_inflow',
        'quantity' => 5,
        'reason' => 'Entrada de reposição',
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'inventory-adjust-1')
        ->post(route('inventory.movements.store'), $payload);

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'inventory-adjust-1')
        ->post(route('inventory.movements.store'), $payload);

    $first->assertRedirect();
    $second->assertRedirect();

    expect(InventoryMovement::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and($product->fresh()->current_stock)->toBe(15)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'inventory-adjust-1')->firstOrFail()->status->value)->toBe('succeeded');
});
