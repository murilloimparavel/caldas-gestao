<?php

use App\Actions\Finance\Commissions\AccrueCommissionsForSale;
use App\Actions\Identity\OnboardTenant;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\ClosingSession;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Customer;
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
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function commissionTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('automatically accrues percentage and fixed commissions upon closing session finalization', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

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

    $profA = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Silva',
    ]);

    $profB = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Santos',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Premium',
        'price_cents' => 10000,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Pomada Modeladora',
        'sale_price_cents' => 5000,
        'cost_price_cents' => 2000,
        'current_stock' => 10,
    ]);

    // Rule 1: Prof A gets 30% on Corte Premium
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $profA->getKey(),
        'service_id' => $service->getKey(),
        'type' => 'percentage',
        'value_rate' => 30,
        'is_active' => true,
    ]);

    // Rule 2: Prof B gets R$ 15,00 fixed (1500 cents) on Pomada Modeladora
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $profB->getKey(),
        'product_id' => $product->getKey(),
        'type' => 'fixed',
        'value_rate' => 1500,
        'is_active' => true,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 15000,
        'final_amount_cents' => 15000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $profA->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Corte Premium',
        'unit_price_cents' => 10000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 10000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'professional_id' => $profB->getKey(),
        'item_type' => 'product',
        'name_snapshot' => 'Pomada Modeladora',
        'unit_price_cents' => 5000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 5000,
    ]);

    $response = $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 15000,
        'payment_method' => 'pix',
    ]);

    $response->assertSessionHasNoErrors();
    $session = ClosingSession::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $response->assertRedirect(route('closing-sessions.show', $session));

    // Verify accruals created
    $accrualsA = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $profA->getKey())
        ->get();

    expect($accrualsA)->toHaveCount(1)
        ->and($accrualsA->first()->commission_amount_cents)->toBe(3000) // 30% of 10000
        ->and($accrualsA->first()->rate_type)->toBe('percentage')
        ->and($accrualsA->first()->rate_value)->toBe(30)
        ->and($accrualsA->first()->status)->toBe('accrued');

    $accrualsB = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $profB->getKey())
        ->get();

    expect($accrualsB)->toHaveCount(1)
        ->and($accrualsB->first()->commission_amount_cents)->toBe(1500) // R$ 15,00 fixed
        ->and($accrualsB->first()->rate_type)->toBe('fixed')
        ->and($accrualsB->first()->rate_value)->toBe(1500)
        ->and($accrualsB->first()->status)->toBe('accrued');

    expect(AuditEvent::query()->where('action', 'commission_accrual.created')->count())->toBe(2);
});

it('accrues a product commission to the seller instead of the executor', function () {
    [$owner, $tenant, $unit, $context] = commissionTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'product',
        'is_active' => true,
    ]);
    $executor = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $seller = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_price_cents' => 5000,
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $seller->getKey(),
        'product_id' => $product->getKey(),
        'type' => 'percentage',
        'value_rate' => 20,
        'is_active' => true,
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'product_id' => $product->getKey(),
        'professional_id' => $executor->getKey(),
        'seller_professional_id' => $seller->getKey(),
        'item_type' => 'product',
        'name_snapshot' => $product->name,
        'unit_price_cents' => 5000,
        'total_cents' => 5000,
    ]);

    $accruals = app(AccrueCommissionsForSale::class)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(1)
        ->and($accruals->first()->professional_id)->toBe($seller->getKey())
        ->and($accruals->first()->sale_item_id)->toBe($item->getKey())
        ->and($accruals->first()->commission_amount_cents)->toBe(1000);
});

it('prioritizes specific rule over generic rule during commission accrual', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Prof Especialista',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Coloração',
        'price_cents' => 20000,
    ]);

    // Generic rule for this professional: 10%
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'service_id' => null,
        'type' => 'percentage',
        'value_rate' => 10,
    ]);

    // Specific rule for this professional on Coloração: 40%
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'service_id' => $service->getKey(),
        'type' => 'percentage',
        'value_rate' => 40,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 20000,
        'final_amount_cents' => 20000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $prof->getKey(),
        'item_type' => 'service',
        'name_snapshot' => 'Coloração',
        'unit_price_cents' => 20000,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 20000,
    ]);

    $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 20000,
        'payment_method' => 'pix',
    ]);

    $accrual = CommissionAccrual::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $prof->getKey())
        ->firstOrFail();

    expect($accrual->rate_value)->toBe(40)
        ->and($accrual->commission_amount_cents)->toBe(8000); // 40% of 20000
});

it('settles accrued commissions generating a settlement and updating accrual statuses', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Liquidação',
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $item1 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
    ]);

    $item2 = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
    ]);

    $accrual1 = CommissionAccrual::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item1->getKey(),
        'commission_amount_cents' => 3500,
        'status' => 'accrued',
    ]);

    $accrual2 = CommissionAccrual::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $prof->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item2->getKey(),
        'commission_amount_cents' => 1500,
        'status' => 'accrued',
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.settle'), [
        'professional_id' => $prof->getKey(),
        'paid_at' => '2026-08-25',
        'notes' => 'Pagamento quinzenal via PIX',
    ]);

    $response->assertSessionHasNoErrors();

    $settlement = CommissionSettlement::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('professional_id', $prof->getKey())
        ->firstOrFail();

    expect($settlement->total_amount_cents)->toBe(5000)
        ->and($settlement->user_id)->toBe($owner->getKey())
        ->and($settlement->notes)->toBe('Pagamento quinzenal via PIX');

    $accrual1->refresh();
    $accrual2->refresh();

    expect($accrual1->status)->toBe('settled')
        ->and($accrual1->settlement_id)->toBe($settlement->getKey())
        ->and($accrual1->settled_at)->not->toBeNull();

    expect($accrual2->status)->toBe('settled')
        ->and($accrual2->settlement_id)->toBe($settlement->getKey())
        ->and($accrual2->settled_at)->not->toBeNull();

    expect(AuditEvent::query()->where('action', 'commission_settlement.completed')->exists())->toBeTrue();
});

it('manages commission rules lifecycle via controller (create, update, delete)', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    // Create Rule
    $createResponse = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $createResponse->assertSessionHasNoErrors();
    $rule = CommissionRule::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    expect($rule->value_rate)->toBe(25)
        ->and($rule->professional_id)->toBe($prof->getKey());

    // Update Rule
    $updateResponse = $this->actingAs($owner)->put(route('commissions.rules.update', $rule), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 35,
        'is_active' => true,
        'lock_version' => $rule->lock_version,
    ]);

    $updateResponse->assertSessionHasNoErrors();
    expect($rule->fresh()->value_rate)->toBe(35)
        ->and($rule->fresh()->lock_version)->toBe(2);

    // Delete Rule
    $deleteResponse = $this->actingAs($owner)->delete(route('commissions.rules.destroy', $rule));
    $deleteResponse->assertSessionHasNoErrors();
    expect(CommissionRule::query()->whereKey($rule->getKey())->exists())->toBeFalse();
});

it('creates one commission rule for every selected service in one request', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $services = Service::factory()->count(2)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'service_ids' => $services->pluck('id')->all(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    expect(CommissionRule::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('professional_id', $professional->getKey())
        ->pluck('service_id')
        ->sort()
        ->values()
        ->all())->toBe($services->pluck('id')->sort()->values()->all());

    $rule = CommissionRule::query()->firstOrFail();
    $updateResponse = $this->actingAs($owner)->put(route('commissions.rules.update', $rule), [
        'professional_id' => $professional->getKey(),
        'service_ids' => [$services->first()->getKey()],
        'type' => 'percentage',
        'value_rate' => 30,
        'lock_version' => $rule->lock_version,
    ]);

    $updateResponse->assertSessionHasErrors('service_ids');
});

it('rejects a service batch atomically when one exact commission rule already exists', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $services = Service::factory()->count(2)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'service_id' => $services->first()->getKey(),
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'service_ids' => $services->pluck('id')->all(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('service_ids');
    expect(CommissionRule::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('professional_id', $professional->getKey())
        ->count())->toBe(1);
});

it('creates one dynamic rule for all services and products including future items', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $serviceResponse = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'scope' => 'service',
        'type' => 'percentage',
        'value_rate' => 50,
        'is_active' => true,
    ]);
    $serviceResponse->assertSessionHasNoErrors();

    $productResponse = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'scope' => 'product',
        'type' => 'percentage',
        'value_rate' => 60,
        'is_active' => true,
    ]);
    $productResponse->assertSessionHasNoErrors();

    expect(CommissionRule::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->get())
        ->toHaveCount(2)
        ->and(CommissionRule::query()->where('scope', 'service')->whereNull('service_id')->exists())->toBeTrue()
        ->and(CommissionRule::query()->where('scope', 'product')->whereNull('product_id')->exists())->toBeTrue();
});

it('applies dynamic service and product rules to items created after the rules', function () {
    [$owner, $tenant, $unit, $context] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'scope' => 'service',
        'type' => 'percentage',
        'value_rate' => 50,
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'scope' => 'product',
        'type' => 'percentage',
        'value_rate' => 60,
    ])->assertSessionHasNoErrors();

    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'open',
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'product_id' => null,
        'professional_id' => $professional->getKey(),
        'total_cents' => 10000,
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'product',
        'service_id' => null,
        'product_id' => $product->getKey(),
        'professional_id' => $professional->getKey(),
        'total_cents' => 10000,
    ]);

    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(2);
    expect($accruals->pluck('rate_value')->sort()->values()->all())->toBe([50, 60]);
});

it('applies a service category rule to current and future services in the category only', function () {
    [$owner, $tenant, $unit, $context] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $careCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'name' => 'Cuidados',
    ]);
    $otherCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'name' => 'Fora da regra',
    ]);

    $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'scope' => 'service_category',
        'category_id' => $careCategory->getKey(),
        'type' => 'percentage',
        'value_rate' => 10,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $currentService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $careCategory->getKey(),
        'price_cents' => 10000,
    ]);
    $futureService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $careCategory->getKey(),
        'price_cents' => 20000,
    ]);
    $outsideService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $otherCategory->getKey(),
        'price_cents' => 30000,
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'open',
    ]);

    foreach ([[$currentService, 10000], [$futureService, 20000], [$outsideService, 30000]] as [$service, $totalCents]) {
        SaleItem::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'sale_id' => $sale->getKey(),
            'item_type' => 'service',
            'service_id' => $service->getKey(),
            'product_id' => null,
            'professional_id' => $professional->getKey(),
            'total_cents' => $totalCents,
        ]);
    }

    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(2)
        ->and($accruals->pluck('gross_amount_cents')->sort()->values()->all())->toBe([10000, 20000])
        ->and($accruals->pluck('commission_amount_cents')->sort()->values()->all())->toBe([1000, 2000]);
});

it('applies a product category fixed rule per quantity and excludes products outside the category', function () {
    [$owner, $tenant, $unit, $context] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $beverages = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'product',
        'name' => 'Bebidas',
    ]);
    $otherCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'product',
        'name' => 'Fora da regra',
    ]);

    $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'professional_id' => $professional->getKey(),
        'scope' => 'product_category',
        'category_id' => $beverages->getKey(),
        'type' => 'fixed',
        'value_rate' => 500,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $includedProduct = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $beverages->getKey(),
    ]);
    $excludedProduct = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $otherCategory->getKey(),
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'open',
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'product',
        'service_id' => null,
        'product_id' => $includedProduct->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 3,
        'total_cents' => 3000,
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'product',
        'service_id' => null,
        'product_id' => $excludedProduct->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 2,
        'total_cents' => 2000,
    ]);

    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(1)
        ->and($accruals->first()->commission_amount_cents)->toBe(1500);
});

it('prioritizes a specific item rule over a category rule and a generic rule', function () {
    [$owner, $tenant, $unit, $context] = commissionTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'price_cents' => 10000,
    ]);

    foreach ([
        ['scope' => 'service_category', 'value_rate' => 10, 'category_id' => $category->getKey()],
        ['scope' => 'service', 'value_rate' => 20, 'service_id' => $service->getKey()],
        ['scope' => 'service', 'value_rate' => 5],
    ] as $ruleData) {
        $this->actingAs($owner)->post(route('commissions.rules.store'), array_merge([
            'professional_id' => $professional->getKey(),
            'type' => 'percentage',
            'is_active' => true,
        ], $ruleData))->assertSessionHasNoErrors();
    }

    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'open',
    ]);
    SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'total_cents' => 10000,
    ]);

    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(1)
        ->and($accruals->first()->rate_value)->toBe(20)
        ->and($accruals->first()->commission_amount_cents)->toBe(2000);
});

it('rejects category commission targets from another tenant or unit', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $foreignCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'type' => 'service',
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'scope' => 'service_category',
        'category_id' => $foreignCategory->getKey(),
        'type' => 'percentage',
        'value_rate' => 10,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('category_id');
    expect(CommissionRule::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->exists())->toBeFalse();
});

it('rejects an exact duplicate product commission rule', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $product = Product::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'product_id' => $product->getKey(),
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'product_id' => $product->getKey(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('product_id');
});

it('creates product commission rules in a batch and rejects duplicate batches atomically', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $products = Product::factory()->count(2)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'product_ids' => $products->pluck('id')->all(),
        'type' => 'percentage',
        'value_rate' => 25,
        'is_active' => true,
    ]);

    $response->assertSessionHasNoErrors();
    expect(CommissionRule::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->whereIn('product_id', $products->pluck('id'))
        ->count())->toBe(2);

    $duplicateResponse = $this->actingAs($owner)->post(route('commissions.rules.store'), [
        'product_ids' => $products->pluck('id')->all(),
        'type' => 'percentage',
        'value_rate' => 30,
        'is_active' => true,
    ]);

    $duplicateResponse->assertSessionHasErrors('product_ids');
    expect(CommissionRule::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->whereIn('product_id', $products->pluck('id'))
        ->count())->toBe(2);
});

it('renders commissions index and professional show pages with Inertia', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Profissional Apresentação',
    ]);

    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);
    Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'inactive',
    ]);

    // Index page
    $indexResponse = $this->actingAs($owner)->get(route('commissions.index'));
    $indexResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finance/commissions/index')
            ->has('professionals')
            ->has('rules')
            ->has('services', 1)
            ->has('metrics')
        );

    // Show page
    $showResponse = $this->actingAs($owner)->get(route('commissions.show', $prof));
    $showResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('finance/commissions/show')
            ->has('professional')
            ->has('accruals')
            ->has('settlements')
            ->has('metrics')
        );
});

it('enforces RBAC permissions for commission viewing, management and settlement', function () {
    [$owner, $tenant, $unit] = commissionTestWorkspace();

    $prof = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $restrictedUser = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $restrictedUser->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $role = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'key' => 'viewer-'.Str::random(6),
    ]);
    $viewPermission = Permission::query()->where('key', 'commission.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $viewPermission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    // Can view
    $this->actingAs($restrictedUser)->get(route('commissions.index'))->assertOk();

    // Cannot create rule (needs commission.manage)
    $this->actingAs($restrictedUser)->post(route('commissions.rules.store'), [
        'professional_id' => $prof->getKey(),
        'type' => 'percentage',
        'value_rate' => 20,
    ])->assertForbidden();

    // Cannot settle (needs commission.settle)
    $this->actingAs($restrictedUser)->post(route('commissions.settle'), [
        'professional_id' => $prof->getKey(),
    ])->assertForbidden();
});
