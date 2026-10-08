<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Closing\ReverseClosingSessionPayment;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Packages\SellCustomerPackage;
use App\Actions\Sales\AddSaleItem;
use App\Actions\Sales\AdjustSale;
use App\Actions\Sales\ApplySaleDiscount;
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
use App\Models\PackageUsageReservation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        'item_type' => 'package', 'package_template_id' => $template->getKey(), 'quantity' => 1, 'discount_cents' => 5000,
    ]);
    (new AddSaleItem)->handle($owner, $context, $packageSale, [
        'item_type' => 'custom', 'name_snapshot' => 'Serviço adicional', 'unit_price_cents' => 7000,
    ]);
    (new ApplySaleDiscount)->handle($owner, $context, $packageSale->fresh(), ['discount_amount_cents' => 2000]);

    expect($packageItem->total_cents)->toBe(25000)
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

it('não ativa pacote gratuito ou totalmente descontado mesmo quando há outro item pago na comanda', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Zero amount package '.Str::random(8),
        'slug' => 'zero-amount-package-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 10000]);
    $freeTemplate = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 0]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package', 'package_template_id' => $template->getKey(), 'discount_cents' => 10000,
    ]);
    $freeItem = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package', 'package_template_id' => $freeTemplate->getKey(),
    ]);
    (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'custom', 'name_snapshot' => 'Serviço pago', 'unit_price_cents' => 5000,
    ]);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);
    $freePackage = CustomerPackage::query()->findOrFail($freeItem->customer_package_id);

    expect($item->total_cents)->toBe(0)
        ->and($freeItem->total_cents)->toBe(0)
        ->and($sale->fresh()->final_amount_cents)->toBe(5000);

    expect(fn () => (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()], 'payment_method' => 'pix',
    ]))->toThrow(ValidationException::class, 'A linha do pacote precisa ter um valor efetivo positivo');

    expect($package->fresh()->status)->toBe('pending')
        ->and($freePackage->fresh()->status)->toBe('pending')
        ->and($sale->fresh()->status)->toBe('open');
});

it('não ativa pacote quando desconto da comanda zera o recebimento líquido', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'No net receipt package '.Str::random(8),
        'slug' => 'no-net-receipt-package-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 10000]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package', 'package_template_id' => $template->getKey(),
    ]);
    (new ApplySaleDiscount)->handle($owner, $context, $sale->fresh(), ['discount_amount_cents' => 10000]);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);

    expect($item->fresh()->total_cents)->toBe(10000)
        ->and($sale->fresh()->final_amount_cents)->toBe(0);

    expect(fn () => (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
    ]))->toThrow(ValidationException::class, 'valor líquido positivo recebido');

    expect($package->fresh()->status)->toBe('pending')
        ->and($package->fresh()->activated_at)->toBeNull()
        ->and($sale->fresh()->status)->toBe('open');
});

it('não ativa pacote quando o fechamento não cobre o total devido', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Underpaid package '.Str::random(8),
        'slug' => 'underpaid-package-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 20000]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package', 'package_template_id' => $template->getKey(),
    ]);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);

    expect(fn () => (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
        'payment_allocations' => [['method' => 'pix', 'amount_cents' => 19999]],
    ]))->toThrow(ValidationException::class);

    expect($sale->fresh()->status)->toBe('open')
        ->and($package->fresh()->status)->toBe('pending')
        ->and($package->fresh()->activated_at)->toBeNull();
});

it('não ativa pacote se ele não estiver vinculado à comanda que contém o item', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Mismatched package sale '.Str::random(8),
        'slug' => 'mismatched-package-sale-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 20000]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $otherSale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(),
        'sale_category_id' => $category->getKey(), 'status' => 'open',
    ]);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package', 'package_template_id' => $template->getKey(),
    ]);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);
    $package->forceFill(['sale_id' => $otherSale->getKey()])->save();

    expect(fn () => (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()], 'payment_method' => 'pix',
    ]))->toThrow(ValidationException::class);

    expect($sale->fresh()->status)->toBe('open')
        ->and($package->fresh()->status)->toBe('pending')
        ->and($package->fresh()->activated_at)->toBeNull();
});

it('inicia a venda de pacote em uma comanda mista e permanece pendente até o fechamento', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package page '.Str::random(8),
        'slug' => 'package-page-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 20000]);
    SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed', 'uniqueness_scope' => 'none', 'name' => 'Pacotes']);

    $payload = [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        // This is the value submitted by the HTML hidden input.
        'start_sale' => '1',
    ];

    $response = $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-page-sale-once')
        ->post(route('customer-packages.store'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sale = Sale::query()->where('customer_id', $customer->getKey())->firstOrFail();
    $item = SaleItem::query()->where('sale_id', $sale->getKey())->firstOrFail();
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);
    $response->assertRedirect(route('sales.show', $sale));

    expect($sale->status)->toBe('open')
        ->and($item->item_type)->toBe('package')
        ->and($item->total_cents)->toBe(20000)
        ->and($package->sale_id)->toBe($sale->getKey())
        ->and($package->status)->toBe('pending');

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'package-page-sale-once')
        ->post(route('customer-packages.store'), $payload)
        ->assertRedirect();

    expect(Sale::query()->where('customer_id', $customer->getKey())->count())->toBe(1)
        ->and(SaleItem::query()->where('sale_id', $sale->getKey())->count())->toBe(1);
});

it('rejeita iniciar a comanda quando o operador só tem permissão de vender pacotes', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Package permissions '.Str::random(8),
        'slug' => 'package-permissions-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $staff = User::factory()->create();
    $membership = Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $staff->getKey(), 'status' => 'active']);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create();
    $role = Role::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Package seller']);
    $permission = Permission::query()->where('key', 'package.sell')->firstOrFail();
    RolePermission::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permission->getKey()]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed', 'uniqueness_scope' => 'none']);

    $this->actingAs($staff)
        ->post(route('customer-packages.store'), [
            'customer_id' => $customer->getKey(),
            'package_template_id' => $template->getKey(),
            'start_sale' => '1',
        ])
        ->assertForbidden();

    expect(Sale::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse()
        ->and(CustomerPackage::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse();
});

it('rejeita atribuição de pacote sem iniciar uma comanda', function (): void {
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

    expect(fn () => (new SellCustomerPackage)->handle($owner, $context, [
        'customer_id' => $customer->getKey(), 'package_template_id' => $template->getKey(),
    ]))->toThrow(ValidationException::class);

    $this->actingAs($owner)->post(route('customer-packages.store'), [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
    ])->assertSessionHasErrors('start_sale');

    expect(CustomerPackage::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse()
        ->and(Sale::query()->where('customer_id', $customer->getKey())->exists())->toBeFalse();
});

it('permite cancelar uma atribuição pendente legada sem linha de comanda', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Cancel pending '.Str::random(8),
        'slug' => 'cancel-pending-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $package = CustomerPackage::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'sale_id' => null,
        'status' => 'pending',
        'activated_at' => null,
        'expires_at' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('customer-packages.cancel', $package), ['reason' => 'Lançamento incorreto'])
        ->assertSessionHasNoErrors();

    expect($package->fresh()->status)->toBe('cancelled');
});

it('cancela o pacote quando qualquer estorno deixa o fechamento abaixo do valor faturado', function (): void {
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
    expect($package->fresh()->status)->toBe('cancelled');
    expect(fn () => (new AdjustSale)->handle($owner, $context, $sale->fresh(), 'Ajuste após estorno parcial'))
        ->toThrow(ValidationException::class);

    (new ReverseClosingSessionPayment)->handle($owner, $context, $payments->firstWhere('amount_cents', 30000), ['reason' => 'Estorno do pacote']);
    expect($package->fresh()->status)->toBe('cancelled');
});

it('impede ajustar a comanda de venda de pacote até cancelar pacote e estornar todo o recebimento', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Adjust package sale '.Str::random(8), 'slug' => 'adjust-package-sale-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 30000]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $item = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);
    $session = (new FinalizeClosingSession)->handle($owner, $context, ['sale_ids' => [$sale->getKey()], 'payment_method' => 'pix']);
    $package = CustomerPackage::query()->findOrFail($item->customer_package_id);
    $payment = ClosingSessionPayment::query()->where('closing_session_id', $session->getKey())->where('is_reversal', false)->firstOrFail();

    expect(fn () => (new AdjustSale)->handle($owner, $context, $sale->fresh(), 'Ajuste antes do estorno'))
        ->toThrow(ValidationException::class);

    expect($sale->fresh()->status)->toBe('finalized')
        ->and($package->fresh()->status)->toBe('active');

    (new ReverseClosingSessionPayment)->handle($owner, $context, $payment, ['reason' => 'Estorno integral da venda do pacote']);

    expect($package->fresh()->status)->toBe('cancelled');

    $adjustedSale = (new AdjustSale)->handle($owner, $context, $sale->fresh(), 'Ajuste após estorno integral');

    expect($adjustedSale->status)->toBe('adjusted');
});

it('impede estornar o recebimento após reservar um uso do pacote e preserva os estados', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Reserved package reversal '.Str::random(8), 'slug' => 'reserved-package-reversal-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 7000]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 30000, 'total_sessions' => 2]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 2]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $packageItem = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);
    $session = (new FinalizeClosingSession)->handle($owner, $context, ['sale_ids' => [$sale->getKey()], 'payment_method' => 'pix']);
    $package = CustomerPackage::query()->findOrFail($packageItem->customer_package_id);
    $payment = ClosingSessionPayment::query()->where('closing_session_id', $session->getKey())->where('is_reversal', false)->firstOrFail();

    $usageSale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $serviceItem = (new AddSaleItem)->handle($owner, $context, $usageSale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]);
    $reservation = PackageUsageReservation::query()->where('sale_item_id', $serviceItem->getKey())->firstOrFail();

    expect($package->status)->toBe('active')
        ->and($package->remaining_sessions)->toBe(2)
        ->and($reservation->status)->toBe('reserved');

    expect(fn () => (new ReverseClosingSessionPayment)->handle($owner, $context, $payment, ['reason' => 'Estorno após reservar sessão']))
        ->toThrow(ValidationException::class, 'consumo ou reserva registrada');

    expect($payment->fresh()->is_reversal)->toBeFalse()
        ->and(ClosingSessionPayment::query()->where('reversal_of_id', $payment->getKey())->exists())->toBeFalse()
        ->and($package->fresh()->status)->toBe('active')
        ->and($package->fresh()->remaining_sessions)->toBe(2)
        ->and($reservation->fresh()->status)->toBe('reserved');
});

it('impede estornar o recebimento após consumir uma sessão e preserva os estados', function (): void {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Consumed package reversal '.Str::random(8), 'slug' => 'consumed-package-reversal-'.Str::lower(Str::random(8))]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 7000]);
    $template = PackageTemplate::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'price_cents' => 30000, 'total_sessions' => 2]);
    $template->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'included_quantity' => 2]);
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'type' => 'mixed']);
    $sale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $packageItem = (new AddSaleItem)->handle($owner, $context, $sale, ['item_type' => 'package', 'package_template_id' => $template->getKey()]);
    $session = (new FinalizeClosingSession)->handle($owner, $context, ['sale_ids' => [$sale->getKey()], 'payment_method' => 'pix']);
    $package = CustomerPackage::query()->findOrFail($packageItem->customer_package_id);
    $payment = ClosingSessionPayment::query()->where('closing_session_id', $session->getKey())->where('is_reversal', false)->firstOrFail();

    $usageSale = Sale::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'sale_category_id' => $category->getKey(), 'status' => 'open']);
    $serviceItem = (new AddSaleItem)->handle($owner, $context, $usageSale, [
        'item_type' => 'service', 'service_id' => $service->getKey(), 'customer_package_id' => $package->getKey(),
    ]);
    (new FinalizeClosingSession)->handle($owner, $context, ['sale_ids' => [$usageSale->getKey()], 'expected_total_cents' => 0]);
    $usage = PackageUsage::query()->where('sale_item_id', $serviceItem->getKey())->firstOrFail();

    expect($package->fresh()->status)->toBe('active')
        ->and($package->fresh()->remaining_sessions)->toBe(1)
        ->and($usage->sessions_consumed)->toBe(1)
        ->and($usage->reversed_at)->toBeNull();

    expect(fn () => (new ReverseClosingSessionPayment)->handle($owner, $context, $payment, ['reason' => 'Estorno após consumo da sessão']))
        ->toThrow(ValidationException::class, 'consumo ou reserva registrada');

    expect($payment->fresh()->is_reversal)->toBeFalse()
        ->and(ClosingSessionPayment::query()->where('reversal_of_id', $payment->getKey())->exists())->toBeFalse()
        ->and($package->fresh()->status)->toBe('active')
        ->and($package->fresh()->remaining_sessions)->toBe(1)
        ->and($usage->fresh()->reversed_at)->toBeNull();
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

it('permite ao vendedor de pacotes lançar pela comanda sem permissão de consumo', function (): void {
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
    $package = (new SellCustomerPackage)->handle($staff, $context, [
        'customer_id' => $customer->getKey(),
        'package_template_id' => $template->getKey(),
        'sale_id' => $sale->getKey(),
    ]);
    $item = SaleItem::query()->where('customer_package_id', $package->getKey())->firstOrFail();

    expect($package->status)->toBe('pending')
        ->and($item->item_type)->toBe('package')
        ->and($package->sale_id)->toBe($sale->getKey());

    (new TransitionSaleStatus)->handle($staff, $context, $sale, 'cancelled');

    expect($package->fresh()->status)->toBe('cancelled');
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
