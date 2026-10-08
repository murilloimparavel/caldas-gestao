<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Closing\ReverseClosingSessionPayment;
use App\Actions\Finance\Commissions\AccrueCommissionsForSale;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Sales\AdjustSale;
use App\Models\Category;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Models\Professional;
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
function packageCommissionWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Pacotes '.Str::random(8),
        'slug' => 'pacotes-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('applies each service rule to its proportional share of a package', function (): void {
    [$owner, $tenant, $unit, $context] = packageCommissionWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $serviceA = Service::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Corte', 'price_cents' => 10000,
    ]);
    $serviceB = Service::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Barba', 'price_cents' => 5000,
    ]);
    $serviceCategory = Category::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service',
    ]);
    $serviceB->forceFill(['category_id' => $serviceCategory->getKey()])->save();
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Combo Corte e Barba', 'price_cents' => 12000,
    ]);
    $template->services()->attach([
        $serviceA->getKey() => ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1],
        $serviceB->getKey() => ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1],
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open', 'total_amount_cents' => 12000, 'final_amount_cents' => 12000,
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(), 'sale_id' => $sale->getKey(), 'name_snapshot' => $template->name,
        'eligible_services_snapshot' => [
            ['id' => $serviceA->getKey(), 'name' => $serviceA->name, 'quantity' => 1, 'price_cents' => 10000],
            ['id' => $serviceB->getKey(), 'name' => $serviceB->name, 'quantity' => 1, 'price_cents' => 5000],
        ],
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'sale_id' => $sale->getKey(), 'item_type' => 'package',
        'service_id' => null, 'product_id' => null, 'package_template_id' => $template->getKey(), 'customer_package_id' => $package->getKey(),
        'professional_id' => $professional->getKey(), 'name_snapshot' => $template->name, 'unit_price_cents' => 12000, 'total_cents' => 12000,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'service_id' => $serviceA->getKey(), 'scope' => 'service', 'type' => 'percentage', 'value_rate' => 10,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'category_id' => $serviceCategory->getKey(), 'scope' => 'service_category', 'type' => 'fixed', 'value_rate' => 1500,
    ]);

    expect($item->fresh()->item_type)->toBe('package');
    expect($sale->fresh()->items()->with('packageTemplate.services')->firstOrFail()->packageTemplate?->services)->toHaveCount(2);
    expect(CommissionRule::query()->where('unit_id', $unit->getKey())->count())->toBe(2);

    expect(SaleItem::query()->where('sale_id', $sale->getKey())->count())->toBe(1);
    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(2)
        ->and($accruals->firstWhere('service_id', $serviceA->getKey())->gross_amount_cents)->toBe(8000)
        ->and($accruals->firstWhere('service_id', $serviceA->getKey())->commission_amount_cents)->toBe(800)
        ->and($accruals->firstWhere('service_id', $serviceB->getKey())->gross_amount_cents)->toBe(4000)
        ->and($accruals->firstWhere('service_id', $serviceB->getKey())->commission_amount_cents)->toBe(1500)
        ->and($accruals->every(fn (CommissionAccrual $accrual): bool => $accrual->source_type === 'package_service'))
        ->toBeTrue()
        ->and(CommissionAccrual::query()->where('sale_item_id', $item->getKey())->sum('gross_amount_cents'))->toBe(12000);
});

it('uses only positive service prices when a package mixes zero and positive prices', function (): void {
    [$owner, $tenant, $unit, $context] = packageCommissionWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $serviceWithPrice = Service::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Corte', 'price_cents' => 10000,
    ]);
    $freeService = Service::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Finalização', 'price_cents' => 0,
    ]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Combo com cortesia', 'price_cents' => 10000,
    ]);
    $template->services()->attach([
        $serviceWithPrice->getKey() => ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1],
        $freeService->getKey() => ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1],
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'total_amount_cents' => 10000, 'final_amount_cents' => 10000,
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(), 'sale_id' => $sale->getKey(), 'name_snapshot' => $template->name,
        'eligible_services_snapshot' => [
            ['id' => $serviceWithPrice->getKey(), 'name' => $serviceWithPrice->name, 'quantity' => 1, 'price_cents' => 10000],
            ['id' => $freeService->getKey(), 'name' => $freeService->name, 'quantity' => 1, 'price_cents' => 0],
        ],
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'sale_id' => $sale->getKey(), 'item_type' => 'package',
        'package_template_id' => $template->getKey(), 'customer_package_id' => $package->getKey(),
        'professional_id' => $professional->getKey(), 'name_snapshot' => $template->name, 'unit_price_cents' => 10000, 'total_cents' => 10000,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'scope' => 'all', 'type' => 'percentage', 'value_rate' => 10,
    ]);

    $accruals = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);

    expect($accruals)->toHaveCount(2)
        ->and($accruals->firstWhere('service_id', $serviceWithPrice->getKey())->gross_amount_cents)->toBe(10000)
        ->and($accruals->firstWhere('service_id', $freeService->getKey())->gross_amount_cents)->toBe(0)
        ->and(CommissionAccrual::query()->where('sale_item_id', $item->getKey())->sum('gross_amount_cents'))->toBe(10000);
});

it('does not duplicate package service accruals when the sale is processed again', function (): void {
    [$owner, $tenant, $unit, $context] = packageCommissionWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 10000]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1]);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey(), 'sale_id' => $sale->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 1, 'price_cents' => $service->price_cents]],
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'sale_id' => $sale->getKey(), 'item_type' => 'package', 'service_id' => null,
        'package_template_id' => $template->getKey(), 'customer_package_id' => $package->getKey(), 'professional_id' => $professional->getKey(), 'total_cents' => 10000,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(), 'service_id' => $service->getKey(),
        'scope' => 'service', 'type' => 'percentage', 'value_rate' => 10,
    ]);

    $first = (new AccrueCommissionsForSale)->handle($owner, $context, $sale);
    $second = (new AccrueCommissionsForSale)->handle($owner, $context, $sale->fresh());

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and(CommissionAccrual::query()->where('sale_item_id', $item->getKey())->count())->toBe(1);
});

it('cancels package service accruals when the paid package sale is reversed and adjusted', function (): void {
    [$owner, $tenant, $unit, $context] = packageCommissionWorkspace();
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 10000]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 10000,
    ]);
    $template->services()->attach($service, [
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 1,
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open', 'total_amount_cents' => 10000, 'final_amount_cents' => 10000,
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(), 'sale_id' => $sale->getKey(), 'status' => 'pending',
        'name_snapshot' => $template->name,
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 1, 'price_cents' => 10000]],
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'sale_id' => $sale->getKey(), 'item_type' => 'package',
        'package_template_id' => $template->getKey(), 'customer_package_id' => $package->getKey(),
        'professional_id' => $professional->getKey(), 'name_snapshot' => $template->name, 'unit_price_cents' => 10000, 'total_cents' => 10000,
    ]);
    CommissionRule::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'professional_id' => $professional->getKey(),
        'service_id' => $service->getKey(), 'scope' => 'service', 'type' => 'percentage', 'value_rate' => 10,
    ]);

    $session = (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()], 'payment_method' => 'pix',
    ]);
    $accrual = CommissionAccrual::query()->where('sale_item_id', $item->getKey())->firstOrFail();
    $payment = $session->payments()->where('is_reversal', false)->firstOrFail();

    expect($package->fresh()->status)->toBe('active')
        ->and($accrual->status)->toBe('accrued');

    (new ReverseClosingSessionPayment)->handle($owner, $context, $payment, ['reason' => 'Estorno integral da venda do pacote']);
    (new AdjustSale)->handle($owner, $context, $sale->fresh(), 'Ajuste após estorno integral');

    expect($package->fresh()->status)->toBe('cancelled')
        ->and($accrual->fresh()->status)->toBe('cancelled');
});
