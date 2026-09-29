<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function saleOrderInventoryWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('creates a sale order, adds product items, and tracks stock consistently before checkout', function () {
    [$owner, $tenant, $unit] = saleOrderInventoryWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Balcão e Produtos',
        'key' => 'balcao-produtos',
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Carlos Drummond',
        'phone' => '(21) 98765-4321',
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Pomada Modeladora Premium',
        'current_stock' => 20,
        'cost_price_cents' => 1500,
        'sale_price_cents' => 4500,
        'is_active' => true,
        'lock_version' => 1,
    ]);

    // 1. Open Sale Order (Comanda)
    $response = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'reference_label' => 'Comanda #101',
        'notes' => 'Cliente aguardando na recepção',
    ]);

    $response->assertRedirect();
    $sale = Sale::query()->where('reference_label', 'Comanda #101')->firstOrFail();

    expect($sale->status)->toBe('open')
        ->and($sale->tenant_id)->toBe($tenant->getKey())
        ->and($sale->unit_id)->toBe($unit->getKey())
        ->and($sale->customer_id)->toBe($customer->getKey())
        ->and($sale->total_amount_cents)->toBe(0)
        ->and($sale->lock_version)->toBe(1);

    // 2. Add Product Item to Comanda
    $itemResponse = $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'product',
        'product_id' => $product->getKey(),
        'quantity' => 2,
        'discount_cents' => 0,
        'lock_version' => $sale->lock_version,
    ]);

    $itemResponse->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->total_amount_cents)->toBe(9000)
        ->and($sale->final_amount_cents)->toBe(9000)
        ->and($sale->lock_version)->toBe(2);

    $saleItem = SaleItem::query()->where('sale_id', $sale->getKey())->firstOrFail();
    expect($saleItem->item_type)->toBe('product')
        ->and($saleItem->product_id)->toBe($product->getKey())
        ->and($saleItem->name_snapshot)->toBe('Pomada Modeladora Premium')
        ->and($saleItem->unit_price_cents)->toBe(4500)
        ->and($saleItem->quantity)->toBe(2)
        ->and($saleItem->total_cents)->toBe(9000);

    // Prior to closing session finalize, inventory remains intact and consistent
    $product->refresh();
    expect($product->current_stock)->toBe(20)
        ->and(InventoryMovement::query()->where('product_id', $product->getKey())->count())->toBe(0);
});

it('finalizes sale order via closing sessions, debits inventory and generates immutable operational receipt with audit trail', function () {
    [$owner, $tenant, $unit] = saleOrderInventoryWorkspace();

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
        'name' => 'Clarice Lispector',
        'phone' => '(21) 99111-2222',
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Shampoo Fortificante',
        'current_stock' => 15,
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
        'discount_amount_cents' => 1000,
        'final_amount_cents' => 9000,
        'lock_version' => 1,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'item_type' => 'product',
        'name_snapshot' => 'Shampoo Fortificante',
        'unit_price_cents' => 5000,
        'quantity' => 2,
        'discount_cents' => 1000,
        'total_cents' => 9000,
    ]);

    // Finalize closing session
    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 9000,
        'payment_method' => 'pix',
        'notes' => 'Pagamento no cartão de débito',
    ]);

    $response->assertSessionHasNoErrors();
    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    // 1. Stock Debit Consistency Validation
    $product->refresh();
    expect($product->current_stock)->toBe(13) // 15 - 2 = 13
        ->and($product->lock_version)->toBe(2);

    $movement = InventoryMovement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('product_id', $product->getKey())
        ->firstOrFail();

    expect($movement->type)->toBe('sale_outflow')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->previous_stock)->toBe(15)
        ->and($movement->resulting_stock)->toBe(13)
        ->and($movement->unit_cost_cents)->toBe(2000)
        ->and($movement->reference_type)->toBe('closing_session')
        ->and($movement->reference_id)->toBe($session->getKey())
        ->and($movement->user_id)->toBe($owner->getKey());

    // 2. Immutable Operational Receipt Validation
    expect($session->status)->toBe('completed')
        ->and($session->unit_id)->toBe($unit->getKey())
        ->and($session->closed_by_user_id)->toBe($owner->getKey())
        ->and($session->closing_subject)->toBe("customer:{$customer->getKey()}")
        ->and($session->expected_total_cents)->toBe(9000)
        ->and($session->final_total_cents)->toBe(9000)
        ->and($session->receipt_number)->toStartWith('REC-')
        ->and($session->receipt_payload)->toBeArray();

    $receipt = $session->receipt_payload;
    expect($receipt['receipt_number'])->toBe($session->receipt_number)
        ->and($receipt['tenant']['id'])->toBe($tenant->getKey())
        ->and($receipt['unit']['id'])->toBe($unit->getKey())
        ->and($receipt['closed_by']['id'])->toBe($owner->getKey())
        ->and($receipt['customer']['name'])->toBe('Clarice Lispector')
        ->and($receipt['totals']['final_total_cents'])->toBe(9000)
        ->and($receipt['totals']['total_discount_cents'])->toBe(1000)
        ->and($receipt['totals']['sales_count'])->toBe(1)
        ->and($receipt['sales'])->toHaveCount(1)
        ->and($receipt['sales'][0]['items'][0]['name'])->toBe('Shampoo Fortificante')
        ->and($receipt['sales'][0]['items'][0]['quantity'])->toBe(2)
        ->and($receipt['notes'])->toBe('Pagamento no cartão de débito');

    // 3. Audit Trail & Immutability Verification
    $sessionAudit = AuditEvent::query()
        ->where('action', 'closing_session.completed')
        ->where('resource_id', $session->getKey())
        ->firstOrFail();

    $inventoryAudit = AuditEvent::query()
        ->where('action', 'inventory.moved')
        ->where('resource_id', $product->getKey())
        ->firstOrFail();

    expect($sessionAudit->actor_user_id)->toBe($owner->getKey())
        ->and($inventoryAudit->actor_user_id)->toBe($owner->getKey());

    // Verify audit append-only immutability
    expect(fn () => $sessionAudit->delete())->toThrow(LogicException::class, 'Audit events are append-only.');
    expect(fn () => $sessionAudit->update(['action' => 'tampered.action']))->toThrow(LogicException::class, 'Audit events are append-only.');

    // 4. Sale status finalized with incremented lock_version
    $sale->refresh();
    expect($sale->status)->toBe('finalized')
        ->and($sale->lock_version)->toBe(2);
});

it('debits inventory only for product items in mixed sales with services', function () {
    [$owner, $tenant, $unit] = saleOrderInventoryWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cera Capilar',
        'current_stock' => 10,
        'cost_price_cents' => 1200,
        'sale_price_cents' => 3000,
        'lock_version' => 1,
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barba Terapia',
        'price_cents' => 4000,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 7000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 7000,
        'lock_version' => 1,
    ]);

    // Service item
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Barba Terapia',
        'unit_price_cents' => 4000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 4000,
    ]);

    // Product item
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'item_type' => 'product',
        'name_snapshot' => 'Cera Capilar',
        'unit_price_cents' => 3000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 3000,
    ]);

    $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 7000,
        'payment_method' => 'pix',
    ])->assertSessionHasNoErrors();

    // Inventory updated only for the product
    $product->refresh();
    expect($product->current_stock)->toBe(9);

    $movements = InventoryMovement::query()->where('tenant_id', $tenant->getKey())->get();
    expect($movements)->toHaveCount(1)
        ->and($movements->first()->product_id)->toBe($product->getKey())
        ->and($movements->first()->quantity)->toBe(1);
});
