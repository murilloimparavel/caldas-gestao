<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\IdempotencyKey;
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
function saleTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('opens a standalone sale without appointment and records snapshots, status history and audit', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia Premium',
        'key' => 'barbearia',
        'type' => 'mixed',
        'uniqueness_scope' => 'customer',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Carlos Silva',
    ]);

    $response = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'notes' => 'Comanda inicial do cliente',
    ]);

    $sale = Sale::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $response->assertRedirect(route('sales.show', $sale));

    expect($sale->unit_id)->toBe($unit->getKey())
        ->and($sale->customer_id)->toBe($customer->getKey())
        ->and($sale->sale_category_id)->toBe($category->getKey())
        ->and($sale->category_key_snapshot)->toBe('barbearia')
        ->and($sale->category_name_snapshot)->toBe('Barbearia Premium')
        ->and($sale->open_context_key)->toBe("customer:{$customer->getKey()}")
        ->and($sale->status)->toBe('open')
        ->and($sale->currency)->toBe('BRL')
        ->and($sale->total_amount_cents)->toBe(0)
        ->and($sale->discount_amount_cents)->toBe(0)
        ->and($sale->final_amount_cents)->toBe(0)
        ->and($sale->lock_version)->toBe(1)
        ->and($sale->appointmentLink)->toBeNull();

    $history = SaleStatusHistory::query()->where('sale_id', $sale->getKey())->firstOrFail();
    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe('open')
        ->and($history->user_id)->toBe($owner->getKey())
        ->and($history->reason)->toBe('Abertura de comanda');

    expect(AuditEvent::query()->where('action', 'sale.opened')->where('resource_id', $sale->getKey())->exists())->toBeTrue();
});

it('opens a sale linked to an appointment with Appointment 0..N Sale and Sale 0..1 Appointment cardinality', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $categoryBarber = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'key' => 'barbearia',
        'type' => 'service',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
    ]);

    $categoryStore = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Loja de Produtos',
        'key' => 'loja',
        'type' => 'product',
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
    ]);

    // First sale linked to appointment (Barbearia)
    $resp1 = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $categoryBarber->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ]);
    $sale1 = Sale::query()->where('sale_category_id', $categoryBarber->getKey())->firstOrFail();
    $resp1->assertRedirect(route('sales.show', $sale1));

    $link1 = AppointmentSaleLink::query()->where('sale_id', $sale1->getKey())->firstOrFail();
    expect($link1->appointment_id)->toBe($appointment->getKey())
        ->and($link1->created_by)->toBe($owner->getKey());

    // Second sale linked to same appointment (Loja) - demonstrating Appointment 0..N Sale
    $this->flushHeaders();
    $resp2 = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $categoryStore->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ]);
    $sale2 = Sale::query()->where('sale_category_id', $categoryStore->getKey())->firstOrFail();
    $resp2->assertRedirect(route('sales.show', $sale2));

    $link2 = AppointmentSaleLink::query()->where('sale_id', $sale2->getKey())->firstOrFail();
    expect($link2->appointment_id)->toBe($appointment->getKey())
        ->and($sale1->getKey())->not->toBe($sale2->getKey());

    expect(AppointmentSaleLink::query()->where('appointment_id', $appointment->getKey())->count())->toBe(2);
});

it('copies the appointment service into a manually opened sale', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
    ]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'price_cents' => 3500,
    ]);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
    ]);
    $appointmentItem = $appointment->items()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'service_name_snapshot' => 'Serviço agendado',
        'duration_minutes' => 45,
        'price_cents' => 3500,
        'position' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ]);

    $sale = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail()
        ->sale;
    $item = $sale->items()->firstOrFail();

    $response->assertRedirect(route('sales.show', $sale));
    expect($sale->total_amount_cents)->toBe(3500)
        ->and($item->service_id)->toBe($service->getKey())
        ->and($item->professional_id)->toBe($professional->getKey())
        ->and($item->name_snapshot)->toBe($appointmentItem->service_name_snapshot)
        ->and($item->unit_price_cents)->toBe(3500)
        ->and($item->source_metadata)->toMatchArray([
            'origin' => 'appointment',
            'appointment_id' => $appointment->getKey(),
            'appointment_item_id' => $appointmentItem->getKey(),
        ]);
});

it('transfers newly available appointment services when reopening an existing manual sale', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
    ]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 3500]);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
    ]);

    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ])->assertRedirect();

    $sale = Sale::query()->where('sale_category_id', $category->getKey())->firstOrFail();
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Item adicionado pelo operador',
        'unit_price_cents' => 1000,
        'total_cents' => 1000,
        'source_id' => null,
        'source_metadata' => null,
    ]);
    $appointmentItem = $appointment->items()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'service_name_snapshot' => 'Serviço agendado depois da abertura',
        'duration_minutes' => 45,
        'price_cents' => 3500,
        'position' => 1,
    ]);

    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    $transferredItem = $sale->items()->where('service_id', $service->getKey())->firstOrFail();

    expect($sale->items()->count())->toBe(2)
        ->and($sale->total_amount_cents)->toBe(4500)
        ->and($transferredItem->name_snapshot)->toBe($appointmentItem->service_name_snapshot)
        ->and($transferredItem->professional_id)->toBe($professional->getKey());

    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'appointment_id' => $appointment->getKey(),
    ])->assertRedirect(route('sales.show', $sale));

    expect($sale->items()->count())->toBe(2)
        ->and($sale->items()->where('name_snapshot', 'Item adicionado pelo operador')->exists())->toBeTrue();
});

it('handles uniqueness scopes and returns existing active sale idempotently', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $customerCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'key' => 'barbearia',
        'type' => 'service',
        'uniqueness_scope' => 'customer',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    // Open first time
    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $customerCategory->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    expect(Sale::query()->where('sale_category_id', $customerCategory->getKey())->count())->toBe(1);
    $firstSale = Sale::query()->where('sale_category_id', $customerCategory->getKey())->firstOrFail();

    // Open second time with same customer in same category - should return existing sale without creating another
    $this->flushHeaders();
    $secondResp = $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $customerCategory->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $secondResp->assertRedirect(route('sales.show', $firstSale));
    expect(Sale::query()->where('sale_category_id', $customerCategory->getKey())->count())->toBe(1);

    // Test reference scope
    $refCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Restaurante Mesa',
        'key' => 'restaurante',
        'type' => 'mixed',
        'uniqueness_scope' => 'reference',
        'is_active' => true,
    ]);

    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $refCategory->getKey(),
        'reference_label' => 'Mesa 12',
    ]);
    $mesaSale = Sale::query()->where('sale_category_id', $refCategory->getKey())->firstOrFail();
    expect($mesaSale->open_context_key)->toBe('reference:mesa-12');

    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.store'), [
        'sale_category_id' => $refCategory->getKey(),
        'reference_label' => 'Mesa 12',
    ])->assertRedirect(route('sales.show', $mesaSale));
    expect(Sale::query()->where('sale_category_id', $refCategory->getKey())->count())->toBe(1);

    // Test none scope
    $noneCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Loja Avulsa',
        'key' => 'loja-avulsa',
        'type' => 'product',
        'uniqueness_scope' => 'none',
        'is_active' => true,
    ]);

    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.store'), ['sale_category_id' => $noneCategory->getKey()]);
    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.store'), ['sale_category_id' => $noneCategory->getKey()]);
    expect(Sale::query()->where('sale_category_id', $noneCategory->getKey())->count())->toBe(2);
});

it('allows regular service items without a package and adds product and custom items with snapshots', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 0,
        'final_amount_cents' => 0,
        'lock_version' => 1,
    ]);

    $serviceCategory = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $serviceCategory->getKey(),
        'name' => 'Corte Degrade',
        'price_cents' => 6000,
        'status' => 'active',
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $serviceCategory->getKey(),
        'name' => 'Pomada Modeladora',
        'sale_price_cents' => 4500,
        'is_active' => true,
    ]);

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);
    $seller = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    // 1. Add Service Item
    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 1,
        'discount_cents' => 1000, // 6000 - 1000 = 5000
        'lock_version' => 1,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->total_amount_cents)->toBe(5000)
        ->and($sale->final_amount_cents)->toBe(5000)
        ->and($sale->lock_version)->toBe(2);

    $serviceItem = SaleItem::query()->where('sale_id', $sale->getKey())->firstOrFail();
    expect($serviceItem->item_type)->toBe('service')
        ->and($serviceItem->service_id)->toBe($service->getKey())
        ->and($serviceItem->professional_id)->toBe($professional->getKey())
        ->and($serviceItem->name_snapshot)->toBe('Corte Degrade')
        ->and($serviceItem->unit_price_cents)->toBe(6000)
        ->and($serviceItem->quantity)->toBe(1)
        ->and($serviceItem->discount_cents)->toBe(1000)
        ->and($serviceItem->total_cents)->toBe(5000);

    // 2. Add Product Item
    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'product',
        'product_id' => $product->getKey(),
        'seller_professional_id' => $seller->getKey(),
        'quantity' => 2,
        'discount_cents' => 500, // (4500 * 2) - 500 = 8500
        'lock_version' => 2,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->total_amount_cents)->toBe(13500) // 5000 + 8500 = 13500
        ->and($sale->final_amount_cents)->toBe(13500)
        ->and($sale->lock_version)->toBe(3);

    // 3. Add Custom Item
    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'custom',
        'name_snapshot' => 'Taxa de Entrega Express',
        'unit_price_cents' => 1500,
        'quantity' => 1,
        'discount_cents' => 0,
        'lock_version' => 3,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->total_amount_cents)->toBe(15000) // 13500 + 1500 = 15000
        ->and($sale->final_amount_cents)->toBe(15000)
        ->and($sale->lock_version)->toBe(4)
        ->and($sale->items()->count())->toBe(3);

    $productItem = SaleItem::query()->where('sale_id', $sale->getKey())->where('item_type', 'product')->firstOrFail();
    expect($productItem->professional_id)->toBeNull()
        ->and($productItem->seller_professional_id)->toBe($seller->getKey())
        ->and($productItem->sellerProfessional->is($seller))->toBeTrue();
});

it('rejects item types incompatible with category type', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $serviceOnlyCategory = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $serviceOnlyCategory->getKey(),
        'status' => 'open',
        'lock_version' => 1,
    ]);

    $category = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'product',
        'product_id' => $product->getKey(),
        'lock_version' => 1,
    ]);

    $response->assertSessionHasErrors('item_type');
    expect(SaleItem::query()->where('sale_id', $sale->getKey())->count())->toBe(0);
});

it('removes item and recalculates sale totals atomically', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 10000,
        'discount_amount_cents' => 1000,
        'final_amount_cents' => 9000,
        'lock_version' => 1,
    ]);

    $item1 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Item 1',
        'unit_price_cents' => 6000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 6000,
    ]);

    $item2 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Item 2',
        'unit_price_cents' => 4000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 4000,
    ]);

    $this->actingAs($owner)
        ->delete(route('sales.items.destroy', ['sale' => $sale, 'item' => $item1]), [
            'lock_version' => 1,
        ])
        ->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->total_amount_cents)->toBe(4000)
        ->and($sale->final_amount_cents)->toBe(3000) // 4000 - 1000 discount
        ->and($sale->lock_version)->toBe(2)
        ->and(SaleItem::query()->where('id', $item1->getKey())->exists())->toBeFalse();

    expect(AuditEvent::query()->where('action', 'sale.item_removed')->where('resource_id', $sale->getKey())->exists())->toBeTrue();
});

it('applies discount with permission check and rejects unauthorized users', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 10000,
        'discount_amount_cents' => 0,
        'final_amount_cents' => 10000,
        'lock_version' => 1,
    ]);

    // 1. Owner has permission sale.discount via OwnerPermissionCatalog
    $this->actingAs($owner)->post(route('sales.discount', $sale), [
        'discount_amount_cents' => 2500,
        'notes' => 'Desconto de cortesia',
        'lock_version' => 1,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->discount_amount_cents)->toBe(2500)
        ->and($sale->final_amount_cents)->toBe(7500)
        ->and($sale->notes)->toBe('Desconto de cortesia')
        ->and($sale->lock_version)->toBe(2);

    expect(AuditEvent::query()->where('action', 'sale.discount_applied')->where('resource_id', $sale->getKey())->exists())->toBeTrue();

    // 2. User without sale.discount permission
    $operator = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $operator->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'operator-no-discount']);
    $viewPermission = Permission::query()->where('key', 'sale.view')->firstOrFail();
    $managePermission = Permission::query()->where('key', 'sale.manage')->firstOrFail();
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $viewPermission->getKey()]);
    RolePermission::query()->create(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $managePermission->getKey()]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->flushHeaders();
    $this->actingAs($operator)->post(route('sales.discount', $sale), [
        'discount_amount_cents' => 3000,
        'lock_version' => 2,
    ])->assertForbidden();
});

it('rejects stale lock_version with 409 conflict', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 5000,
        'lock_version' => 2,
    ]);

    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'custom',
        'name_snapshot' => 'Item Conflito',
        'unit_price_cents' => 1000,
        'lock_version' => 1, // Stale version
    ])->assertStatus(409);
});

it('transitions sale status through lifecycle and records history', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'uniqueness_scope' => 'none',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'lock_version' => 1,
    ]);

    // 1. open -> ready_to_bill
    $this->actingAs($owner)->post(route('sales.transition', $sale), [
        'status' => 'ready_to_bill',
        'reason' => 'Atendimento finalizado na cadeira',
        'lock_version' => 1,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->status)->toBe('ready_to_bill')
        ->and($sale->lock_version)->toBe(2);

    // 2. ready_to_bill -> open (reabertura para adicionar novo item)
    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.transition', $sale), [
        'status' => 'open',
        'reason' => 'Cliente solicitou serviço extra',
        'lock_version' => 2,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->status)->toBe('open')
        ->and($sale->lock_version)->toBe(3);

    // 3. open -> cancelled
    $this->flushHeaders();
    $this->actingAs($owner)->post(route('sales.transition', $sale), [
        'status' => 'cancelled',
        'reason' => 'Cliente desistiu',
        'lock_version' => 3,
    ])->assertRedirect(route('sales.show', $sale));

    $sale->refresh();
    expect($sale->status)->toBe('cancelled')
        ->and($sale->lock_version)->toBe(4);

    $histories = SaleStatusHistory::query()->where('sale_id', $sale->getKey())->orderBy('created_at')->get();
    expect($histories->count())->toBe(3)
        ->and($histories[0]->from_status)->toBe('open')
        ->and($histories[0]->to_status)->toBe('ready_to_bill')
        ->and($histories[1]->from_status)->toBe('ready_to_bill')
        ->and($histories[1]->to_status)->toBe('open')
        ->and($histories[2]->from_status)->toBe('open')
        ->and($histories[2]->to_status)->toBe('cancelled');
});

it('rejects reusing a transition idempotency key for a different status payload', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'open',
        'lock_version' => 1,
    ]);

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-transition-reused-key')
        ->post(route('sales.transition', $sale), [
            'status' => 'ready_to_bill',
            'lock_version' => 1,
        ])
        ->assertRedirect(route('sales.show', $sale));

    $this->flushHeaders();

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-transition-reused-key')
        ->post(route('sales.transition', $sale), [
            'status' => 'open',
            'reason' => 'Reabertura de comanda para inclusão de itens',
            'lock_version' => 2,
        ])
        ->assertStatus(409);

    $sale->refresh();

    expect($sale->status)->toBe('ready_to_bill')
        ->and($sale->lock_version)->toBe(2);
});

it('replays an idempotent sale creation mutation with X-Idempotency-Key', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'uniqueness_scope' => 'none',
    ]);

    $payload = [
        'sale_category_id' => $category->getKey(),
        'notes' => 'Venda com idempotência',
    ];

    $first = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-open-key-1')
        ->post(route('sales.store'), $payload);

    $sale = Sale::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

    $second = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'sale-open-key-1')
        ->post(route('sales.store'), $payload);

    $first->assertRedirect(route('sales.show', $sale));
    $second->assertRedirect(route('sales.show', $sale));
    expect(Sale::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', 'sale-open-key-1')->firstOrFail()->status->value)->toBe('succeeded');
});

it('renders sale index with enriched metrics, categories and customers', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'open',
        'final_amount_cents' => 5000,
    ]);

    Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'ready_to_bill',
        'final_amount_cents' => 3000,
    ]);

    $expectedCustomerCount = Customer::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('status', 'active')
        ->count();

    $response = $this->actingAs($owner)->get(route('sales.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/index')
            ->has('sales.data', 2)
            ->has('categories', 1)
            ->has('customers', $expectedCustomerCount)
            ->where('metrics.open_count', 1)
            ->where('metrics.ready_count', 1)
            ->where('metrics.today_total_cents', 8000)
        );
});

it('renders sale show with auxiliary services, products, professionals and categories', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Salão',
        'is_active' => true,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);

    $serviceCategory = Category::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $serviceCategory->getKey(),
        'status' => 'active',
    ]);

    Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $serviceCategory->getKey(),
        'is_active' => true,
    ]);

    Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner)->get(route('sales.show', $sale));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sales/show')
            ->where('sale.id', $sale->getKey())
            ->has('services', 1)
            ->has('products', 1)
            ->has('professionals', 1)
            ->has('categories', 1)
        );
});

it('includes sale links in calendar payload and loads sale categories through selector options', function () {
    [$owner, $tenant, $unit] = saleTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbearia',
        'is_active' => true,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'status' => 'active']);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'status' => 'active']);

    $appointment = Appointment::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'open',
        'reference_label' => 'Cadeira 01',
    ]);

    AppointmentSaleLink::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'appointment_id' => $appointment->getKey(),
        'sale_id' => $sale->getKey(),
    ]);

    expect($appointment->saleLinks()->count())->toBe(1)
        ->and($appointment->saleLink)->not->toBeNull()
        ->and($appointment->saleLink->sale_id)->toBe($sale->getKey());

    $response = $this->actingAs($owner)->get(route('calendar.index'));

    $saleCategoryOptionsResponse = $this->actingAs($owner)
        ->getJson(route('selector-options.index', ['resource' => 'sale-categories']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/index')
            ->missing('options.sale_categories')
            ->has('appointments.0.sale_link')
            ->where('appointments.0.sale_link.sale_id', $sale->getKey())
        );

    $saleCategoryOptionsResponse->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $category->getKey())
        ->assertJsonPath('data.0.name', 'Barbearia');
});
