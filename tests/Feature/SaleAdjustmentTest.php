<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\InventoryMovement;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function saleAdjustmentTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('adjusts a finalized sale, replenishing inventory and cancelling commissions atomically', function () {
    [$owner, $tenant, $unit, $context] = saleAdjustmentTestWorkspace();

    $saleCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Shampoo Especial',
        'current_stock' => 10,
        'cost_price_cents' => 2000,
        'sale_price_cents' => 5000,
        'lock_version' => 1,
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Corte Premium',
        'price_cents' => 8000,
    ]);

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    // Rule: 10% on services
    CommissionRule::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'service_id' => $service->getKey(),
        'type' => 'percentage',
        'value_rate' => 10,
        'is_active' => true,
        'lock_version' => 1,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $saleCategory->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'open',
        'total_amount_cents' => 18000,
        'final_amount_cents' => 18000,
        'lock_version' => 1,
    ]);

    $productItem = SaleItem::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'product',
        'product_id' => $product->getKey(),
        'name_snapshot' => 'Shampoo Especial',
        'unit_price_cents' => 5000,
        'quantity' => 2,
        'discount_cents' => 0,
        'total_cents' => 10000,
    ]);

    $serviceItem = SaleItem::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'name_snapshot' => 'Corte Premium',
        'unit_price_cents' => 8000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 8000,
    ]);

    // Finalize closing session (moves product stock from 10 to 8, accrues 800 cents commission)
    $closingSession = (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 18000,
    ]);

    $sale->refresh();
    $product->refresh();
    expect($sale->status)->toBe('finalized')
        ->and($product->current_stock)->toBe(8);

    $accrual = CommissionAccrual::query()
        ->where('sale_id', $sale->getKey())
        ->where('professional_id', $professional->getKey())
        ->firstOrFail();
    expect($accrual->status)->toBe('accrued')
        ->and($accrual->commission_amount_cents)->toBe(800);

    // Perform Adjustment (Estorno Compensatório)
    $response = $this->actingAs($owner)->post(route('sales.adjust', $sale), [
        'reason' => 'Cliente devolveu os produtos e cancelou atendimento por insatisfação.',
        'lock_version' => $sale->lock_version,
    ]);

    $response->assertRedirect(route('sales.show', $sale));

    // Verify Sale
    $sale->refresh();
    expect($sale->status)->toBe('adjusted')
        ->and($sale->lock_version)->toBe(3);

    // Verify Product Stock was replenished by 2 (+2 gain)
    $product->refresh();
    expect($product->current_stock)->toBe(10);

    $gainMovement = InventoryMovement::query()
        ->where('product_id', $product->getKey())
        ->where('type', 'adjustment_gain')
        ->where('reference_type', 'sale')
        ->where('reference_id', $sale->getKey())
        ->firstOrFail();

    expect($gainMovement->quantity)->toBe(2)
        ->and($gainMovement->previous_stock)->toBe(8)
        ->and($gainMovement->resulting_stock)->toBe(10)
        ->and($gainMovement->user_id)->toBe($owner->getKey());

    // Verify Commission was cancelled
    $accrual->refresh();
    expect($accrual->status)->toBe('cancelled')
        ->and($accrual->lock_version)->toBe(2);

    // Verify Sale Status History
    $history = SaleStatusHistory::query()
        ->where('sale_id', $sale->getKey())
        ->where('to_status', 'adjusted')
        ->firstOrFail();

    expect($history->from_status)->toBe('finalized')
        ->and($history->user_id)->toBe($owner->getKey())
        ->and($history->reason)->toBe('Cliente devolveu os produtos e cancelou atendimento por insatisfação.');

    // Verify Audit Trail
    expect(AuditEvent::query()
        ->where('action', 'sale.adjusted')
        ->where('resource_id', $sale->getKey())
        ->exists()
    )->toBeTrue();
});

it('rejects adjustment for sales that are not finalized', function (string $invalidStatus) {
    [$owner, $tenant, $unit] = saleAdjustmentTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => $invalidStatus,
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('sales.adjust', $sale), [
        'reason' => 'Tentativa inválida de estorno',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasErrors('status');
    $sale->refresh();
    expect($sale->status)->toBe($invalidStatus);
})->with(['open', 'ready_to_bill', 'cancelled', 'draft', 'adjusted']);

it('rejects adjustment with 409 conflict when lock_version does not match', function () {
    [$owner, $tenant, $unit] = saleAdjustmentTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'lock_version' => 3,
    ]);

    $this->actingAs($owner)->post(route('sales.adjust', $sale), [
        'reason' => 'Estorno com versão antiga',
        'lock_version' => 2, // Conflito
    ])->assertStatus(409);

    $sale->refresh();
    expect($sale->status)->toBe('finalized');
});

it('requires a valid non-empty reason for adjustment', function () {
    [$owner, $tenant, $unit] = saleAdjustmentTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'lock_version' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('sales.adjust', $sale), [
        'reason' => '  ',
        'lock_version' => 1,
    ]);

    $response->assertSessionHasErrors('reason');
    $sale->refresh();
    expect($sale->status)->toBe('finalized');
});

it('enforces RBAC permissions for sale.adjust and allows authorized operators', function () {
    [$owner, $tenant, $unit] = saleAdjustmentTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'lock_version' => 1,
    ]);

    // 1. User without adjust or manage permission => 403 Forbidden
    $unauthorizedUser = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $unauthorizedUser->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'viewer-role']);
    $viewPermission = Permission::query()->where('key', 'sale.view')->firstOrFail();
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $viewPermission->getKey()]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($unauthorizedUser)->post(route('sales.adjust', $sale), [
        'reason' => 'Tentativa não autorizada',
        'lock_version' => 1,
    ])->assertForbidden();

    // 2. User with sale.adjust permission => Allowed
    $adjustPermission = Permission::query()->where('key', 'sale.adjust')->firstOrFail();
    $inventoryPermission = Permission::query()->where('key', 'inventory.manage')->firstOrFail();
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $adjustPermission->getKey()]);
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $inventoryPermission->getKey()]);

    $this->flushHeaders();
    $this->actingAs($unauthorizedUser)->post(route('sales.adjust', $sale), [
        'reason' => 'Estorno autorizado pelo operador com permissão sale.adjust',
        'lock_version' => 1,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->status)->toBe('adjusted');
});

it('replays sale adjustment mutation idempotently with X-Idempotency-Key', function () {
    [$owner, $tenant, $unit] = saleAdjustmentTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'finalized',
        'lock_version' => 1,
    ]);

    $payload = [
        'reason' => 'Estorno idempotente da comanda',
        'lock_version' => 1,
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-adjust-key-1')
        ->post(route('sales.adjust', $sale), $payload);

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-adjust-key-1')
        ->post(route('sales.adjust', $sale), $payload);

    $first->assertRedirect(route('sales.show', $sale));
    $second->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->status)->toBe('adjusted')
        ->and($sale->lock_version)->toBe(2);

    expect(IdempotencyKey::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('key', 'sale-adjust-key-1')
        ->firstOrFail()
        ->status->value
    )->toBe('succeeded');
});
