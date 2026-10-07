<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\AdjustSale;
use App\Actions\Sales\RemoveSaleItem;
use App\Actions\Sales\TransitionSaleStatus;
use App\Models\CashMovement;
use App\Models\ClosingSessionPayment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\IdempotencyKey;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Permission;
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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: TenantContext} */
function packageIntegrityWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package integrity '.Str::random(8),
        'slug' => 'package-integrity-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit, TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey())];
}

it('snapshots package terms and rejects a source sale for another customer', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Corte']);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte mensal',
        'price_cents' => 24900,
        'total_sessions' => 4,
        'validity_days' => 30,
    ]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $foreignCustomerSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $otherCustomer->getKey(),
    ]);

    $this->actingAs($owner)
        ->post(route('customer-packages.store'), [
            'customer_id' => $customer->getKey(),
            'package_template_id' => $template->getKey(),
            'sale_id' => $foreignCustomerSale->getKey(),
            'payment_method' => 'pix',
        ])
        ->assertSessionHasErrors('sale_id');

    $this->actingAs($owner)
        ->post(route('customer-packages.store'), [
            'customer_id' => $customer->getKey(),
            'package_template_id' => $template->getKey(),
            'payment_method' => 'pix',
        ])
        ->assertSessionHasNoErrors();

    $package = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($package->name_snapshot)->toBe('Corte mensal')
        ->and($package->price_cents_snapshot)->toBe(24900)
        ->and($package->total_sessions_snapshot)->toBe(4)
        ->and($package->validity_days_snapshot)->toBe(30)
        ->and($package->eligible_services_snapshot)->toBe([['id' => $service->getKey(), 'name' => 'Corte', 'quantity' => 1]])
        ->and($package->serviceBalances()->first()->remaining_quantity)->toBe(1);

    $template->update(['name' => 'Novo nome', 'price_cents' => 29900]);
    $package->refresh();

    expect($package->name_snapshot)->toBe('Corte mensal')
        ->and($package->price_cents_snapshot)->toBe(24900);
});

it('requires sale-linked package consumption to go through the sale closing flow', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $eligibleService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $ineligibleService = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $eligibleService->getKey(), 'name' => $eligibleService->name]],
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $eligibleItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $eligibleService->getKey(),
    ]);
    $ineligibleItem = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'service_id' => $ineligibleService->getKey(),
    ]);

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package, [
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $eligibleItem->getKey(),
    ]))->toThrow(InvalidArgumentException::class, 'apenas pelo fechamento');

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package->fresh(), [
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $ineligibleItem->getKey(),
    ]))->toThrow(InvalidArgumentException::class, 'apenas pelo fechamento');

    expect($package->fresh()->remaining_sessions)->toBe(5)
        ->and(PackageUsage::query()->where('customer_package_id', $package->getKey())->exists())->toBeFalse();
});

it('reserves package sessions on service items, splits uncovered quantity, and consumes the reservation at closing', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 5000]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 0,
        'final_amount_cents' => 0,
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name, 'quantity' => 2]],
        'total_sessions' => 2,
        'remaining_sessions' => 2,
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 2,
        'remaining_quantity' => 2,
    ]);

    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
        'quantity' => 3,
    ])->assertSessionHasNoErrors();
    $item = SaleItem::query()->where('sale_id', $sale->getKey())->firstOrFail();

    expect($item->unit_price_cents)->toBe(5000)
        ->and($item->quantity)->toBe(3)
        ->and($item->covered_quantity)->toBe(2)
        ->and($item->total_cents)->toBe(5000)
        ->and($sale->fresh()->final_amount_cents)->toBe(5000)
        ->and(PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->value('status'))->toBe('reserved')
        ->and($package->fresh()->remaining_sessions)->toBe(2);

    expect(fn () => (new AddSaleItem)->handle($owner, $context, $sale->fresh(), [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ]))->toThrow(ValidationException::class);

    (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 5000,
        'payment_method' => 'pix',
    ]);

    expect($package->fresh()->remaining_sessions)->toBe(0)
        ->and($package->fresh()->status)->toBe('exhausted')
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(0)
        ->and(PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->value('status'))->toBe('consumed')
        ->and(PackageUsage::query()->where('sale_item_id', $item->getKey())->value('sessions_consumed'))->toBe(2);
});

it('closes a fully package-covered service without a payment allocation', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 5000]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
        'total_amount_cents' => 0,
        'final_amount_cents' => 0,
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'remaining_sessions' => 1,
        'total_sessions' => 1,
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 1,
    ]);

    $this->actingAs($owner)->post(route('sales.items.store', $sale), [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->post(route('closing-sessions.store'), [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => 0,
    ])->assertSessionHasNoErrors();

    expect($sale->fresh()->status)->toBe('finalized')
        ->and($package->fresh()->remaining_sessions)->toBe(0)
        ->and(ClosingSessionPayment::query()->exists())->toBeFalse()
        ->and(CashMovement::query()->exists())->toBeFalse();
});

it('releases an open package reservation when its service item is removed', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open', 'total_amount_cents' => 0, 'final_amount_cents' => 0]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(), 'allocated_quantity' => 1, 'remaining_quantity' => 1,
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]);

    (new RemoveSaleItem)->handle($owner, $context, $sale, $item);

    expect(PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->value('status'))->toBe('released')
        ->and($sale->fresh()->final_amount_cents)->toBe(0)
        ->and($package->fresh()->remaining_sessions)->toBe(5)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(1);
});

it('releases package reservations when an open sale is cancelled', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'service']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open', 'total_amount_cents' => 0, 'final_amount_cents' => 0]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(), 'allocated_quantity' => 1, 'remaining_quantity' => 1,
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]);

    (new TransitionSaleStatus)->handle($owner, $context, $sale, 'cancelled');

    expect(PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->value('status'))->toBe('released')
        ->and($package->fresh()->remaining_sessions)->toBe(5)
        ->and($package->serviceBalances()->where('service_id', $service->getKey())->value('remaining_quantity'))->toBe(1);
});

it('prevents manual package consumption from taking sessions already reserved by a sale', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'remaining_sessions' => 1,
        'total_sessions' => 1,
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 1,
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ]);

    expect(fn () => (new ConsumePackageSession)->handle($owner, $context, $package, [
        'service_id' => $service->getKey(),
        'sessions_consumed' => 1,
    ]))->toThrow(ConflictHttpException::class);

    expect($package->fresh()->remaining_sessions)->toBe(1)
        ->and(PackageUsageReservation::query()->where('sale_item_id', $item->getKey())->value('status'))->toBe('reserved');
});

it('restores consumed package balance when its sale is adjusted without reactivating an expired package', function (): void {
    [$owner, $tenant, $unit, $context] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'status' => 'finalized',
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'remaining_sessions' => 0,
        'total_sessions' => 1,
        'expires_at' => now()->subDay()->toDateString(),
        'status' => 'expired',
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    $serviceBalance = CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 0,
    ]);
    $item = SaleItem::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'quantity' => 1,
        'covered_quantity' => 1,
        'total_cents' => 0,
    ]);
    $usage = PackageUsage::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);
    $reservation = PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $item->getKey(),
        'user_id' => $owner->getKey(),
        'package_usage_id' => $usage->getKey(),
        'sessions_reserved' => 1,
        'status' => 'consumed',
    ]);

    (new AdjustSale)->handle($owner, $context, $sale, 'Estorno para corrigir a comanda');

    expect($package->fresh()->remaining_sessions)->toBe(1)
        ->and($package->fresh()->status)->toBe('expired')
        ->and($serviceBalance->fresh()->remaining_quantity)->toBe(1)
        ->and($usage->fresh()->reversed_at)->not->toBeNull()
        ->and($reservation->fresh()->status)->toBe('released');
});

it('tracks quantities and consumption independently for each package service', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $beard = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Barba']);
    $botox = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'name' => 'Botox']);

    $this->actingAs($owner)->post(route('packages.store'), [
        'name' => 'Combo personalizado',
        'price_cents' => 30000,
        'total_sessions' => 5,
        'validity_days' => 90,
        'service_ids' => [$beard->getKey(), $botox->getKey()],
        'service_quantities' => [$beard->getKey() => 3, $botox->getKey() => 2],
    ])->assertSessionHasNoErrors();

    $template = PackageTemplate::query()->where('name', 'Combo personalizado')->firstOrFail();

    $this->actingAs($owner)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'payment_method' => 'pix',
    ])->assertSessionHasNoErrors();

    $package = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();
    expect($package->serviceBalances()->where('service_id', $beard->getKey())->value('allocated_quantity'))->toBe(3)
        ->and($package->serviceBalances()->where('service_id', $botox->getKey())->value('allocated_quantity'))->toBe(2);

    $this->actingAs($owner)->post(route('customer-packages.consume', $package), [
        'service_id' => $beard->getKey(),
        'sessions_consumed' => 2,
    ])->assertSessionHasNoErrors();

    expect($package->serviceBalances()->where('service_id', $beard->getKey())->value('remaining_quantity'))->toBe(1)
        ->and($package->serviceBalances()->where('service_id', $botox->getKey())->value('remaining_quantity'))->toBe(2)
        ->and(PackageUsage::query()->where('customer_package_id', $package->getKey())->value('service_id'))->toBe($beard->getKey());
});

it('requires package consumption permission when adding a package-covered service to a sale', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $staff = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $staff->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $role = Role::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Sales operator without package access',
    ]);
    $saleManagePermission = Permission::query()->where('key', 'sale.manage')->firstOrFail();
    RolePermission::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $tenant->getKey(),
        'role_id' => $role->getKey(),
        'permission_id' => $saleManagePermission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(),
        'status' => 'open',
    ]);
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
    ]);
    CustomerPackageService::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'service_id' => $service->getKey(),
        'allocated_quantity' => 1,
        'remaining_quantity' => 1,
    ]);

    $this->actingAs($staff)->post(route('sales.items.store', $sale), [
        'item_type' => 'service',
        'service_id' => $service->getKey(),
        'customer_package_id' => $package->getKey(),
    ])->assertForbidden();

    expect(SaleItem::query()->where('sale_id', $sale->getKey())->exists())->toBeFalse()
        ->and(PackageUsageReservation::query()->where('customer_package_id', $package->getKey())->exists())->toBeFalse();
});

it('scopes package idempotency to the customer package route resource', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $firstPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $secondPackage = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
    ]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    foreach ([$firstPackage, $secondPackage] as $package) {
        $package->forceFill([
            'eligible_services_snapshot' => [['id' => $service->getKey(), 'name' => $service->name]],
        ])->save();
        CustomerPackageService::query()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'customer_package_id' => $package->getKey(),
            'service_id' => $service->getKey(),
            'allocated_quantity' => 5,
            'remaining_quantity' => 5,
        ]);
    }

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-consume-scope')
        ->post(route('customer-packages.consume', $firstPackage), ['service_id' => $service->getKey()])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-consume-scope')
        ->post(route('customer-packages.consume', $secondPackage), ['service_id' => $service->getKey()])
        ->assertStatus(409);

    expect($firstPackage->fresh()->remaining_sessions)->toBe(4)
        ->and($secondPackage->fresh()->remaining_sessions)->toBe(5)
        ->and(IdempotencyKey::query()->where('key', 'package-consume-scope')->value('resource_id'))
        ->toBe($firstPackage->getKey());
});

it('reverses a usage once, restores the balance, and records governance events', function (): void {
    [$owner, $tenant, $unit] = packageIntegrityWorkspace();
    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'total_sessions' => 2,
        'remaining_sessions' => 1,
    ]);
    $usage = PackageUsage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $package->getKey(),
        'sessions_consumed' => 1,
        'user_id' => $owner->getKey(),
    ]);

    $response = $this->actingAs($owner)
        ->post(route('customer-packages.usages.reverse', [$package, $usage]), ['reason' => 'Lançamento em duplicidade']);
    $response->assertSessionHasNoErrors();

    expect($package->fresh()->remaining_sessions)->toBe(2)
        ->and($usage->fresh()->reversed_at)->not->toBeNull()
        ->and($usage->fresh()->reversed_by_user_id)->toBe($owner->getKey());

    $this->assertDatabaseHas('audit_events', ['action' => 'package_usage.reversed', 'resource_id' => $usage->getKey()]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'package_usage.reversed', 'aggregate_id' => $usage->getKey()]);

    $this->actingAs($owner)
        ->post(route('customer-packages.usages.reverse', [$package, $usage]), ['reason' => 'Tentativa repetida'])
        ->assertStatus(409);
});

it('expires packages in an idempotent batch with audit and outbox records', function (): void {
    [, $tenant, $unit] = packageIntegrityWorkspace();
    $expired = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->subDay()->toDateString(),
        'status' => 'active',
    ]);
    $current = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay()->toDateString(),
        'status' => 'active',
    ]);

    $this->artisan('app:expire-customer-packages')->assertSuccessful();

    expect($expired->fresh()->status)->toBe('expired')
        ->and($current->fresh()->status)->toBe('active');
    $this->assertDatabaseHas('audit_events', ['action' => 'customer_package.expired', 'resource_id' => $expired->getKey()]);
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'customer_package.expired', 'aggregate_id' => $expired->getKey()]);

    $this->artisan('app:expire-customer-packages')->assertSuccessful();
    expect(CustomerPackage::query()->where('status', 'expired')->count())->toBe(1);
});
