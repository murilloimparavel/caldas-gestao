<?php

use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Sales\AddSaleItem;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageTemplate;
use App\Models\PackageUsageReservation;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

function createTestCustomerPackage(
    User $owner,
    TenantContext $context,
    Customer $customer,
    array $servicesWithQuantity,
    int $priceCents = 20000,
    int $validityDays = 30
): CustomerPackage {
    $tenantId = $context->tenant->getKey();
    $unitId = $context->unit->getKey();

    $mixedCategory = SaleCategory::query()
        ->where('tenant_id', $tenantId)
        ->where('unit_id', $unitId)
        ->where('type', 'mixed')
        ->first();

    if ($mixedCategory === null) {
        $mixedCategory = SaleCategory::factory()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'type' => 'mixed',
            'is_active' => true,
        ]);
    }

    $totalSessions = array_sum(array_column($servicesWithQuantity, 'quantity'));

    $template = PackageTemplate::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'price_cents' => $priceCents,
        'total_sessions' => $totalSessions,
        'validity_days' => $validityDays,
        'is_active' => true,
    ]);

    foreach ($servicesWithQuantity as $item) {
        $template->services()->attach($item['service'], [
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'included_quantity' => $item['quantity'],
        ]);
    }

    $professional = Professional::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
    ]);

    $sale = Sale::factory()->create([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $mixedCategory->getKey(),
        'status' => 'open',
    ]);

    (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package',
        'package_template_id' => $template->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 1,
    ]);

    (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => $priceCents,
        'payment_method' => 'pix',
        'payments' => [
            ['amount_cents' => $priceCents, 'payment_method' => 'pix'],
        ],
    ]);

    return CustomerPackage::query()
        ->where('tenant_id', $tenantId)
        ->where('unit_id', $unitId)
        ->where('customer_id', $customer->getKey())
        ->where('status', 'active')
        ->latest('id')
        ->firstOrFail();
}

it('returns active packages with available balances and deducts reservations', function () {
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Studio Beleza '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);

    $serviceHair = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Corte Cabelo',
        'price_cents' => 6000,
    ]);

    $serviceBeard = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barba Terapia',
        'price_cents' => 4000,
    ]);

    $pkg = createTestCustomerPackage($owner, $context, $customer, [
        ['service' => $serviceHair, 'quantity' => 4],
        ['service' => $serviceBeard, 'quantity' => 2],
    ]);

    $mixedCategory = SaleCategory::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('unit_id', $unit->getKey())
        ->where('type', 'mixed')
        ->firstOrFail();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    // Criar uma venda com item de serviço para anexar a reserva válida
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'sale_category_id' => $mixedCategory->getKey(),
        'status' => 'open',
    ]);

    $saleItem = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'service',
        'service_id' => $serviceHair->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 1,
    ]);

    // Criar uma reserva ativa de 1 corte de cabelo para simular saldo reservado
    PackageUsageReservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_package_id' => $pkg->getKey(),
        'sale_id' => $sale->getKey(),
        'sale_item_id' => $saleItem->getKey(),
        'service_id' => $serviceHair->getKey(),
        'user_id' => $owner->getKey(),
        'sessions_reserved' => 1,
        'status' => 'reserved',
    ]);

    $response = $this->actingAs($owner)
        ->getJson("/customers/{$customer->getKey()}/active-packages");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'total_sessions',
                    'remaining_sessions',
                    'reserved_sessions',
                    'available_sessions',
                    'expires_at',
                    'services' => [
                        '*' => [
                            'service_id',
                            'service_name',
                            'remaining_quantity',
                            'reserved_quantity',
                            'available_quantity',
                        ],
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['remaining_sessions'])->toBe(6);
    expect($data[0]['reserved_sessions'])->toBe(1);
    expect($data[0]['available_sessions'])->toBe(5);

    $services = collect($data[0]['services'])->keyBy('service_id');
    expect($services->has($serviceHair->getKey()))->toBeTrue();
    expect($services[$serviceHair->getKey()]['remaining_quantity'])->toBe(4);
    expect($services[$serviceHair->getKey()]['reserved_quantity'])->toBe(1);
    expect($services[$serviceHair->getKey()]['available_quantity'])->toBe(3);

    expect($services->has($serviceBeard->getKey()))->toBeTrue();
    expect($services[$serviceBeard->getKey()]['remaining_quantity'])->toBe(2);
    expect($services[$serviceBeard->getKey()]['reserved_quantity'])->toBe(0);
    expect($services[$serviceBeard->getKey()]['available_quantity'])->toBe(2);
});

it('rejects access to active packages across tenants', function () {
    $ownerA = User::factory()->create();
    $tenantA = (new OnboardTenant)->handle($ownerA, ['name' => 'Studio A '.Str::random(8)]);
    $ownerB = User::factory()->create();
    $tenantB = (new OnboardTenant)->handle($ownerB, ['name' => 'Studio B '.Str::random(8)]);

    $customerB = Customer::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'unit_id' => $tenantB->units()->firstOrFail()->getKey(),
    ]);

    $response = $this->actingAs($ownerA)
        ->getJson("/customers/{$customerB->getKey()}/active-packages");

    $response->assertForbidden();
});
