<?php

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\SynchronizeAppointmentSale;
use App\Actions\Appointments\TransferAppointmentItemsToSale;
use App\Actions\Closing\FinalizeClosingSession;
use App\Actions\Identity\OnboardTenant;
use App\Actions\PublicBooking\CreatePublicAppointment;
use App\Actions\Sales\AddSaleItem;
use App\Models\AppointmentSaleLink;
use App\Models\AvailabilityRule;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\OnlineBookingSetting;
use App\Models\PackageTemplate;
use App\Models\PackageUsageReservation;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Helper para criar e pagar um pacote para o cliente.
 */
function createAndPayPackage(
    User $owner,
    TenantContext $context,
    Customer $customer,
    array $servicesWithQuantity, // [[service, quantity]]
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

    $saleItem = (new AddSaleItem)->handle($owner, $context, $sale, [
        'item_type' => 'package',
        'package_template_id' => $template->getKey(),
        'professional_id' => $professional->getKey(),
        'quantity' => 1,
    ]);

    (new FinalizeClosingSession)->handle($owner, $context, [
        'sale_ids' => [$sale->getKey()],
        'expected_total_cents' => $priceCents,
        'payment_method' => 'pix',
    ]);

    return CustomerPackage::query()->findOrFail($saleItem->customer_package_id);
}

/**
 * Cria workspace base para testes de cobertura de pacote em agendamentos.
 *
 * @return array{owner: User, tenant: Tenant, unit: Unit, customer: Customer, professional: Professional, service: Service, category: SaleCategory, context: TenantContext, date: CarbonImmutable}
 */
function appointmentPackageWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Pkg Coverage '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $unit->update(['timezone' => 'America/Sao_Paulo']);

    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'mixed',
        'is_active' => true,
    ]);

    $unit->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $category->getKey(),
        'online_booking_enabled' => true,
    ]);

    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'phone' => '11999998888',
        'phone_normalized' => '11999998888',
        'status' => 'active',
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => true,
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'price_cents' => 10000,
        'duration_minutes' => 60,
        'online_booking_enabled' => true,
    ]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);

    $date = CarbonImmutable::now('America/Sao_Paulo')->next(CarbonImmutable::MONDAY)->setTime(10, 0);

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => $date->dayOfWeek,
        'starts_at' => '08:00:00',
        'ends_at' => '18:00:00',
        'timezone' => 'America/Sao_Paulo',
    ]);

    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'minimum_notice_minutes' => 0,
        'public_hours' => [(string) $date->dayOfWeek => ['enabled' => true, 'starts_at' => '08:00', 'ends_at' => '18:00']],
    ]);

    return [
        'owner' => $owner,
        'tenant' => $tenant,
        'unit' => $unit->fresh(),
        'customer' => $customer,
        'professional' => $professional,
        'service' => $service,
        'category' => $category,
        'context' => $context,
        'date' => $date,
    ];
}

it('a) agendamento interno com cliente com pacote ativo cria comanda com total_cents = 0, covered_quantity = 1, vínculo ao pacote e reserva reservada', function (): void {
    $ws = appointmentPackageWorkspace();

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [['service' => $ws['service'], 'quantity' => 2]],
    );

    expect($package->status)->toBe('active')
        ->and($package->remaining_sessions)->toBe(2);

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 60,
        ],
    );

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);

    expect($sale->items)->toHaveCount(1);
    $item = $sale->items->first();

    expect($item->customer_package_id)->toBe($package->getKey())
        ->and($item->covered_quantity)->toBe(1)
        ->and($item->total_cents)->toBe(0)
        ->and($sale->total_amount_cents)->toBe(0);

    $reservation = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('sale_item_id', $item->getKey())
        ->firstOrFail();

    expect($reservation->status)->toBe('reserved')
        ->and($reservation->sessions_reserved)->toBe(1)
        ->and($reservation->service_id)->toBe($ws['service']->getKey());
});

it('b) agendamento online com cliente existente com pacote ativo cria comanda com item zerado e reserva no pacote', function (): void {
    $ws = appointmentPackageWorkspace();

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [['service' => $ws['service'], 'quantity' => 3]],
    );

    $appointment = app(CreatePublicAppointment::class)->handle(
        $ws['tenant'],
        $ws['unit'],
        [
            'name' => $ws['customer']->name,
            'phone' => '11999998888',
            'starts_at' => $ws['date']->toIso8601String(),
            'professional_id' => $ws['professional']->getKey(),
            'service_ids' => [$ws['service']->getKey()],
        ],
    );

    expect($appointment->customer_id)->toBe($ws['customer']->getKey());

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);
    expect($sale->items)->toHaveCount(1);

    $item = $sale->items->first();
    expect($item->customer_package_id)->toBe($package->getKey())
        ->and($item->covered_quantity)->toBe(1)
        ->and($item->total_cents)->toBe(0);

    $reservation = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('sale_item_id', $item->getKey())
        ->firstOrFail();

    expect($reservation->status)->toBe('reserved')
        ->and($reservation->sessions_reserved)->toBe(1);
});

it('c) agendamento com múltiplos serviços quando o pacote só tem 1 sessão restante cobre 1 serviço e cobra o outro normalmente', function (): void {
    $ws = appointmentPackageWorkspace();

    $service2 = Service::factory()->create([
        'tenant_id' => $ws['tenant']->getKey(),
        'unit_id' => $ws['unit']->getKey(),
        'price_cents' => 8000,
        'duration_minutes' => 30,
        'online_booking_enabled' => true,
    ]);
    $ws['professional']->services()->attach($service2, ['tenant_id' => $ws['tenant']->getKey(), 'unit_id' => $ws['unit']->getKey()]);

    // Pacote com apenas 1 sessão de $ws['service'] e 0 do service2 (ou 1 sessão no total elegível para ambos)
    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [
            ['service' => $ws['service'], 'quantity' => 1],
            ['service' => $service2, 'quantity' => 1],
        ],
    );

    // Agora vamos simular que já resta apenas 1 sessão restante no pacote (por exemplo, forçando remaining_sessions = 1)
    $package->update(['remaining_sessions' => 1]);

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'service_ids' => [$ws['service']->getKey(), $service2->getKey()],
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 90,
        ],
    );

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);
    expect($sale->items)->toHaveCount(2);

    $coveredItem = $sale->items->firstWhere('customer_package_id', $package->getKey());
    $chargedItem = $sale->items->firstWhere('customer_package_id', null);

    expect($coveredItem)->not->toBeNull()
        ->and($coveredItem->covered_quantity)->toBe(1)
        ->and($coveredItem->total_cents)->toBe(0)
        ->and($chargedItem)->not->toBeNull()
        ->and($chargedItem->covered_quantity)->toBe(0)
        ->and($chargedItem->total_cents)->toBe(8000)
        ->and($sale->total_amount_cents)->toBe(8000);

    // Verifica que apenas 1 reserva foi criada
    $reservations = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('status', 'reserved')
        ->get();

    expect($reservations)->toHaveCount(1)
        ->and($reservations->first()->sale_item_id)->toBe($coveredItem->getKey());
});

it('d) cancelamento do agendamento cancela a comanda e libera a reserva do pacote', function (): void {
    $ws = appointmentPackageWorkspace();

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [['service' => $ws['service'], 'quantity' => 2]],
    );

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 60,
        ],
    );

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);
    $item = $sale->items->first();

    $reservation = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('sale_item_id', $item->getKey())
        ->firstOrFail();

    expect($reservation->status)->toBe('reserved');

    app(CancelAppointment::class)->handle($ws['owner'], $ws['context'], $appointment, [
        'lock_version' => $appointment->lock_version,
        'reason' => 'Cliente cancelou',
    ]);

    expect($sale->fresh()->status)->toBe('cancelled')
        ->and($reservation->fresh()->status)->toBe('released')
        ->and($reservation->fresh()->released_at)->not->toBeNull();
});

it('e) transferência manual de itens de agendamento para comanda aberta cobre serviço pelo pacote ativo', function (): void {
    $ws = appointmentPackageWorkspace();

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [['service' => $ws['service'], 'quantity' => 2]],
    );

    // Desativa automação para criar agendamento sem comanda automática
    $ws['unit']->update(['appointment_sales_automation_enabled' => false]);
    $ws['context'] = $ws['context']->revalidate();

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 60,
        ],
    );

    // Cria comanda manual aberta
    $manualSale = Sale::factory()->create([
        'tenant_id' => $ws['tenant']->getKey(),
        'unit_id' => $ws['unit']->getKey(),
        'customer_id' => $ws['customer']->getKey(),
        'sale_category_id' => $ws['category']->getKey(),
        'status' => 'open',
    ]);

    app(TransferAppointmentItemsToSale::class)->handle(
        $ws['owner'],
        $ws['context'],
        $manualSale,
        $appointment,
    );

    $manualSale->refresh();
    expect($manualSale->items)->toHaveCount(1);

    $item = $manualSale->items->first();
    expect($item->customer_package_id)->toBe($package->getKey())
        ->and($item->covered_quantity)->toBe(1)
        ->and($item->total_cents)->toBe(0);

    $reservation = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('sale_item_id', $item->getKey())
        ->firstOrFail();

    expect($reservation->status)->toBe('reserved')
        ->and($reservation->sessions_reserved)->toBe(1);
});

it('f) sincronização do agendamento mantém total_cents = 0 para item coberto por pacote', function (): void {
    $ws = appointmentPackageWorkspace();

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [['service' => $ws['service'], 'quantity' => 2]],
    );

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 60,
        ],
    );

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);
    $item = $sale->items->first();
    expect($item->total_cents)->toBe(0)
        ->and($item->covered_quantity)->toBe(1);

    // Agora sincroniza o item com novo snapshot/preço
    $appointmentItem = $appointment->items()->firstOrFail();
    $appointmentItem->update(['price_cents' => 15000]);

    $syncedSale = app(SynchronizeAppointmentSale::class)->handle(
        $ws['owner'],
        $ws['context'],
        $appointment,
        $appointmentItem,
    );

    $syncedItem = $syncedSale->items->firstWhere('id', $item->getKey());
    expect($syncedItem->total_cents)->toBe(0)
        ->and($syncedItem->covered_quantity)->toBe(1)
        ->and($syncedSale->total_amount_cents)->toBe(0);
});

it('cobre múltiplos serviços distintos pertencentes ao mesmo pacote quando ambos têm saldo', function (): void {
    $ws = appointmentPackageWorkspace();

    $service2 = Service::factory()->create([
        'tenant_id' => $ws['tenant']->getKey(),
        'unit_id' => $ws['unit']->getKey(),
        'price_cents' => 8000,
        'duration_minutes' => 30,
        'online_booking_enabled' => true,
    ]);
    $ws['professional']->services()->attach($service2, ['tenant_id' => $ws['tenant']->getKey(), 'unit_id' => $ws['unit']->getKey()]);

    $package = createAndPayPackage(
        $ws['owner'],
        $ws['context'],
        $ws['customer'],
        [
            ['service' => $ws['service'], 'quantity' => 2],
            ['service' => $service2, 'quantity' => 2],
        ],
    );

    expect($package->remaining_sessions)->toBe(4);

    $appointment = app(CreateAppointment::class)->handle(
        $ws['owner'],
        $ws['context'],
        [
            'customer_id' => $ws['customer']->getKey(),
            'service_id' => $ws['service']->getKey(),
            'service_ids' => [$ws['service']->getKey(), $service2->getKey()],
            'professional_id' => $ws['professional']->getKey(),
            'starts_at' => $ws['date']->toIso8601String(),
            'duration_minutes' => 90,
        ],
    );

    $link = AppointmentSaleLink::query()
        ->where('appointment_id', $appointment->getKey())
        ->firstOrFail();

    $sale = Sale::query()->with('items')->findOrFail($link->sale_id);
    expect($sale->items)->toHaveCount(2);

    foreach ($sale->items as $item) {
        expect($item->customer_package_id)->toBe($package->getKey())
            ->and($item->covered_quantity)->toBe(1)
            ->and($item->total_cents)->toBe(0);
    }

    expect($sale->total_amount_cents)->toBe(0);

    $reservations = PackageUsageReservation::query()
        ->where('customer_package_id', $package->getKey())
        ->where('status', 'reserved')
        ->get();

    expect($reservations)->toHaveCount(2);
    expect($reservations->pluck('service_id')->all())->toEqualCanonicalizing([
        $ws['service']->getKey(),
        $service2->getKey(),
    ]);
});
