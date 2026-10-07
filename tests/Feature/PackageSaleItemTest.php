<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Closing\ReverseClosingSessionPayment;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\CancelCustomerPackage;
use App\Actions\Marketing\Packages\SellCustomerPackage;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\RemoveSaleItem;
use App\Actions\Sales\TransitionSaleStatus;
use App\Models\ClosingSessionPayment;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\PackageTemplate;
use App\Models\PackageUsage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

it('fatura pacote pela comanda e cobre usos futuros sem nova cobrança', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package sale '.Str::random(8),
        'slug' => 'package-sale-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 7000]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(),
        'price_cents' => 30000, 'total_sessions' => 2, 'validity_days' => 30,
    ]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 2]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);

    $packageSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $packageItem = (new AddSaleItem)->handle($owner, $context, $packageSale, [
        'item_type' => 'package', 'package_template_id' => $template->getKey(), 'quantity' => 1,
    ]);

    expect($packageItem->total_cents)->toBe(30000)
        ->and(CustomerPackage::query()->whereKey($packageItem->customer_package_id)->value('status'))->toBe('pending');

    (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$packageSale->getKey()], 'expected_total_cents' => 30000, 'payment_method' => 'pix',
    ]);

    $package = CustomerPackage::query()->findOrFail($packageItem->customer_package_id);
    expect($package->status)->toBe('active')
        ->and($package->activated_at)->not->toBeNull()
        ->and($package->expires_at?->toDateString())->toBe($package->activated_at->copy()->addDays(30)->toDateString());

    $usageSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $serviceItem = (new AddSaleItem)->handle($owner, $context, $usageSale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]);

    expect($serviceItem->total_cents)->toBe(0)->and($serviceItem->covered_quantity)->toBe(1);

    (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$usageSale->getKey()], 'expected_total_cents' => 0,
    ]);

    expect($package->fresh()->remaining_sessions)->toBe(1)
        ->and(PackageUsage::query()->where('sale_item_id', $serviceItem->getKey())->value('sessions_consumed'))->toBe(1);
});

it('mantém atribuição direta pendente sem iniciar validade ou expiração', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Pending package '.Str::random(8),
        'slug' => 'pending-package-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'validity_days' => 1,
    ]);

    $package = (new SellCustomerPackage)->handle($owner, $context, [
        'customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey(),
    ]);

    expect($package->status)->toBe('pending')
        ->and($package->expires_at)->toBeNull()
        ->and($package->activated_at)->toBeNull()
        ->and($package->sale_id)->toBeNull();

    $this->artisan('app:expire-customer-packages')->assertSuccessful();
    expect($package->fresh()->status)->toBe('pending');
});

it('permite cancelar somente uma atribuição pendente sem linha de comanda', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Cancel pending '.Str::random(8),
        'slug' => 'cancel-pending-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $this->actingAs($owner)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey(),
    ])->assertSessionHasNoErrors();
    $package = CustomerPackage::query()->where('customer_id', $customer->getKey())->firstOrFail();

    $this->actingAs($owner)
        ->post(route('customer-packages.cancel', $package), ['reason' => 'Lançamento incorreto'])
        ->assertSessionHasNoErrors();

    expect($package->fresh()->status)->toBe('cancelled');
});

it('só cancela pacote no estorno que zera o recebimento do fechamento', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Split reversal '.Str::random(8), 'slug' => 'split-reversal-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 30000]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);
    (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'custom', 'name_snapshot' => 'Serviço extra', 'unit_price_cents' => 7000]);
    $session = (new FinalizeClosingSession)->handle($owner, $context, ['sale_ids' => [$sale->getKey()], 'payment_allocations' => [
        ['method' => 'pix', 'amount_cents' => 30000], ['method' => 'debit_card', 'amount_cents' => 7000],
    ]]);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);
    $payments = ClosingSessionPayment::query()->where('closing_session_id', $session->getKey())->orderBy('amount_cents')->get();

    (new ReverseClosingSessionPayment)->handle($owner, $context, $payments->firstWhere('amount_cents', 7000), ['reason' => 'Estorno do serviço extra']);
    expect($package->fresh()->status)->toBe('active');

    (new ReverseClosingSessionPayment)->handle($owner, $context, $payments->firstWhere('amount_cents', 30000), ['reason' => 'Estorno do pacote']);
    expect($package->fresh()->status)->toBe('cancelled');
});

it('cancela pacote pendente lançado quando a comanda é cancelada', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Cancel sale package '.Str::random(8), 'slug' => 'cancel-sale-package-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);

    (new TransitionSaleStatus)->handle($owner, $context, $sale, 'cancelled');

    expect(CustomerPackage::query()->whereKey($item->customer_package_id)->value('status'))->toBe('cancelled');
});

it('cancela atribuição pendente diretamente ligada à comanda mesmo sem item', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Direct package sale '.Str::random(8), 'slug' => 'direct-package-sale-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $package = (new SellCustomerPackage)->handle($owner, $context, ['customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey(), 'sale_id' => $sale->getKey()]);

    (new TransitionSaleStatus)->handle($owner, $context, $sale, 'cancelled');

    expect($package->fresh()->status)->toBe('cancelled');
});

it('permite ao vendedor de pacotes lançar e cancelar sem permissão de consumo', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Package seller only '.Str::random(8), 'slug' => 'package-seller-only-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $staff = User::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $staff->getKey(), 'status' => 'active']);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Package seller only']);
    foreach (['sale.manage', 'package.sell'] as $permissionKey) {
        $permission = Permission::query()->where('key', $permissionKey)->firstOrFail();
        RolePermission::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permission->getKey()]);
    }
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();
    $context = TenantContext::forUser($staff, $tenant->getKey(), $unit->getKey());
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $ownerContext = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $package = (new SellCustomerPackage)->handle($owner, $ownerContext, ['customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey()]);

    (new CancelCustomerPackage)->handle($staff, $context, $package, ['reason' => 'Erro de lançamento']);
    expect($package->fresh()->status)->toBe('cancelled');

    $package = (new SellCustomerPackage)->handle($owner, $ownerContext, ['customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey()]);

    $item = (new AddSaleItem)->handle($staff, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey(), 'customer_package_id' => $package->getKey()]);

    expect($item->customer_package_id)->toBe($package->getKey());
});

it('permite relançar a mesma instância depois de remover o item da comanda', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Relist package '.Str::random(8), 'slug' => 'relist-package-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $firstItem = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);

    (new RemoveSaleItem)->handle($owner, $context, $sale, $firstItem);
    $secondItem = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey(), 'customer_package_id' => $firstItem->customer_package_id]);

    expect($secondItem->getKey())->not->toBe($firstItem->getKey())
        ->and(Sale::findOrFail($sale->getKey())->items()->where('customer_package_id', $firstItem->customer_package_id)->count())->toBe(1);
});
