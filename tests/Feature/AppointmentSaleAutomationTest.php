<?php

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\CreateAppointmentSale;
use App\Actions\Appointments\UpdateAppointment;
use App\Actions\Identity\OnboardTenant;
use App\Actions\PublicBooking\CreatePublicAppointment;
use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\AvailabilityRule;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\MembershipUnit;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array{owner: User, tenant: Tenant, unit: Unit, customer: Customer, professional: Professional, service: Service, category: SaleCategory, context: TenantContext} */
function appointmentSaleAutomationWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, ['name' => 'Automation '.Str::random(8)]);
    $unit = $tenant->units()->firstOrFail();
    $unit->update(['timezone' => 'America/Sao_Paulo']);
    $customer = Customer::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'price_cents' => 12500,
        'duration_minutes' => 60,
    ]);
    $professional->services()->attach($service, ['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()]);
    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'weekday' => 0,
    ]);
    $category = SaleCategory::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
    ]);

    return [
        'owner' => $owner,
        'tenant' => $tenant,
        'unit' => $unit,
        'customer' => $customer,
        'professional' => $professional,
        'service' => $service,
        'category' => $category,
        'context' => TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()),
    ];
}

/** @return array<string, mixed> */
function appointmentSaleAutomationPayload(Customer $customer, Professional $professional, Service $service, string $startsAt): array
{
    return [
        'customer_id' => $customer->getKey(),
        'service_id' => $service->getKey(),
        'professional_id' => $professional->getKey(),
        'starts_at' => $startsAt,
        'duration_minutes' => 60,
    ];
}

/** @return array{workspace: array<string, mixed>, appointment: Appointment, sale: Sale} */
function appointmentSaleAutomationCancellationFixture(): array
{
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();
    $appointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload(
            $workspace['customer'],
            $workspace['professional'],
            $workspace['service'],
            '2030-02-10 10:00',
        ),
    );

    return [
        'workspace' => $workspace,
        'appointment' => $appointment,
        'sale' => Sale::query()->firstOrFail(),
    ];
}

it('keeps appointment sale automation off by default and creates a sale when enabled', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $payload = appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 10:00');

    $firstAppointment = app(CreateAppointment::class)->handle($workspace['owner'], $workspace['context'], $payload);

    expect($workspace['unit']->fresh()->appointment_sales_automation_enabled)->toBeFalse()
        ->and(Sale::query()->count())->toBe(0)
        ->and(AppointmentSaleLink::query()->count())->toBe(0);

    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();

    $secondAppointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 12:00'),
    );
    $sale = Sale::query()->with('items')->firstOrFail();
    $item = $sale->items->firstOrFail();

    expect(Sale::query()->count())->toBe(1)
        ->and(AppointmentSaleLink::query()->where('appointment_id', $firstAppointment->getKey())->exists())->toBeFalse()
        ->and(AppointmentSaleLink::query()->where('appointment_id', $secondAppointment->getKey())->firstOrFail()->sale_id)->toBe($sale->getKey())
        ->and($sale->sale_category_id)->toBe($workspace['category']->getKey())
        ->and($sale->customer_id)->toBe($workspace['customer']->getKey())
        ->and($sale->source_metadata)->toMatchArray(['origin' => 'appointment_automation', 'created_automatically' => true])
        ->and($item->service_id)->toBe($workspace['service']->getKey())
        ->and($item->professional_id)->toBe($workspace['professional']->getKey())
        ->and($item->unit_price_cents)->toBe(12500)
        ->and($item->total_cents)->toBe(12500)
        ->and($item->source_metadata)->toMatchArray(['origin' => 'appointment_automation', 'created_automatically' => true]);
});

it('is idempotent when automation is requested again for the same appointment', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();
    $appointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 10:00'),
    );

    $action = app(CreateAppointmentSale::class);
    $firstSale = $action->handle($workspace['owner'], $workspace['context'], $appointment);
    $secondSale = $action->handle($workspace['owner'], $workspace['context'], $appointment);

    expect($firstSale?->getKey())->toBe($secondSale?->getKey())
        ->and(Sale::query()->count())->toBe(1)
        ->and(AppointmentSaleLink::query()->where('appointment_id', $appointment->getKey())->count())->toBe(1)
        ->and(SaleItem::query()->where('sale_id', $firstSale?->getKey())->count())->toBe(1);
});

it('rejects appointments from another tenant or unit', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, ['name' => 'Other '.Str::random(8)]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $foreignAppointment = Appointment::factory()->create([
        'tenant_id' => $otherTenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
    ]);

    expect(fn () => app(CreateAppointmentSale::class)->handle($workspace['owner'], $workspace['context'], $foreignAppointment))
        ->toThrow(AuthorizationException::class);

    $otherUnit = Unit::factory()->create(['tenant_id' => $workspace['tenant']->getKey()]);
    MembershipUnit::factory()
        ->forMembership($workspace['context']->membership)
        ->forUnit($otherUnit)
        ->create();
    $sameTenantAppointment = Appointment::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);
    $otherUnitContext = TenantContext::forUser($workspace['owner'], $workspace['tenant']->getKey(), $otherUnit->getKey());

    expect(fn () => app(CreateAppointmentSale::class)->handle($workspace['owner'], $otherUnitContext, $sameTenantAppointment))
        ->toThrow(AuthorizationException::class);
});

it('rejects an inactive or product category as the automation default', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $productCategory = SaleCategory::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'type' => 'product',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
    ]);
    $appointment = Appointment::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'customer_id' => $workspace['customer']->getKey(),
        'professional_id' => $workspace['professional']->getKey(),
        'starts_at' => '2030-02-10 10:00',
        'ends_at' => '2030-02-10 11:00',
    ]);
    $appointment->items()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'service_id' => $workspace['service']->getKey(),
        'professional_id' => $workspace['professional']->getKey(),
        'service_name_snapshot' => $workspace['service']->name,
        'duration_minutes' => 60,
        'price_cents' => 12500,
        'currency' => 'BRL',
        'position' => 1,
    ]);
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $productCategory->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();

    expect(fn () => app(CreateAppointmentSale::class)->handle($workspace['owner'], $workspace['context'], $appointment))
        ->toThrow(ValidationException::class);

    $workspace['unit']->update(['appointment_default_sale_category_id' => $workspace['category']->getKey()]);
    $workspace['category']->update(['is_active' => false]);
    $workspace['context'] = $workspace['context']->revalidate();

    expect(fn () => app(CreateAppointmentSale::class)->handle($workspace['owner'], $workspace['context'], $appointment))
        ->toThrow(ValidationException::class);
});

it('creates one automatic sale with its link and item for an online appointment', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'online_booking_enabled' => true,
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['service']->update(['online_booking_enabled' => true]);
    $workspace['professional']->update(['online_booking_enabled' => true]);
    $date = CarbonImmutable::now($workspace['unit']->timezone)->addDays(7)->startOfDay();
    AvailabilityRule::query()
        ->where('tenant_id', $workspace['tenant']->getKey())
        ->where('unit_id', $workspace['unit']->getKey())
        ->where('professional_id', $workspace['professional']->getKey())
        ->update(['weekday' => $date->dayOfWeek, 'timezone' => $workspace['unit']->timezone]);

    $appointment = app(CreatePublicAppointment::class)->handle($workspace['tenant'], $workspace['unit'], [
        'service_id' => $workspace['service']->getKey(),
        'professional_id' => $workspace['professional']->getKey(),
        'starts_at' => $date->setTime(10, 0)->toIso8601String(),
        'name' => 'Online Automation Customer',
        'phone' => '+55 (11) 99999-4321',
    ]);
    $sale = Sale::query()->firstOrFail();
    $link = AppointmentSaleLink::query()->firstOrFail();
    $item = SaleItem::query()->firstOrFail();

    expect(Sale::query()->count())->toBe(1)
        ->and(AppointmentSaleLink::query()->count())->toBe(1)
        ->and(SaleItem::query()->count())->toBe(1)
        ->and($appointment->source)->toBe('online')
        ->and($link->appointment_id)->toBe($appointment->getKey())
        ->and($link->sale_id)->toBe($sale->getKey())
        ->and($sale->source_id)->toBe(sprintf('appointment-automation:%s:%s:%s', $workspace['tenant']->getKey(), $workspace['unit']->getKey(), $appointment->getKey()))
        ->and($sale->source_metadata)->toMatchArray([
            'origin' => 'appointment_automation',
            'created_automatically' => true,
            'appointment_id' => $appointment->getKey(),
        ])
        ->and($item->sale_id)->toBe($sale->getKey())
        ->and($item->source_id)->toBe(sprintf('appointment-automation-item:%s:%s:%s', $workspace['tenant']->getKey(), $workspace['unit']->getKey(), $appointment->items->firstOrFail()->getKey()))
        ->and($item->service_id)->toBe($workspace['service']->getKey())
        ->and($item->source_metadata)->toMatchArray([
            'origin' => 'appointment_automation',
            'created_automatically' => true,
            'appointment_item_id' => $appointment->items->firstOrFail()->getKey(),
        ]);
});

it('toggles appointment automation through the sale category HTTP update', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $headers = $this->withHeader('X-Tenant-Id', $workspace['tenant']->getKey())
        ->withHeader('X-Unit-Id', $workspace['unit']->getKey())
        ->actingAs($workspace['owner']);
    $categoryPayload = [
        'name' => $workspace['category']->name,
        'key' => $workspace['category']->key,
        'type' => 'service',
        'uniqueness_scope' => 'appointment',
        'is_active' => true,
        'lock_version' => 1,
    ];

    $headers->patch(route('sale-categories.update', $workspace['category']), [
        ...$categoryPayload,
        'is_default_for_appointments' => true,
    ])->assertRedirect();

    expect($workspace['unit']->fresh()->appointment_sales_automation_enabled)->toBeTrue()
        ->and($workspace['unit']->fresh()->appointment_default_sale_category_id)->toBe($workspace['category']->getKey());

    $headers->patch(route('sale-categories.update', $workspace['category']), [
        ...$categoryPayload,
        'is_default_for_appointments' => false,
        'lock_version' => 2,
    ])->assertRedirect();

    expect($workspace['unit']->fresh()->appointment_sales_automation_enabled)->toBeFalse()
        ->and($workspace['unit']->fresh()->appointment_default_sale_category_id)->toBeNull();

    $productCategory = SaleCategory::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'type' => 'product',
        'is_active' => true,
        'lock_version' => 1,
    ]);
    $headers->patch(route('sale-categories.update', $productCategory), [
        'name' => $productCategory->name,
        'key' => $productCategory->key,
        'type' => 'product',
        'uniqueness_scope' => 'none',
        'is_active' => true,
        'is_default_for_appointments' => true,
        'lock_version' => 1,
    ])->assertSessionHasErrors('is_default_for_appointments');

    $inactiveCategory = SaleCategory::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'type' => 'service',
        'is_active' => false,
        'lock_version' => 1,
    ]);
    $headers->patch(route('sale-categories.update', $inactiveCategory), [
        'name' => $inactiveCategory->name,
        'key' => $inactiveCategory->key,
        'type' => 'service',
        'uniqueness_scope' => 'none',
        'is_active' => false,
        'is_default_for_appointments' => true,
        'lock_version' => 1,
    ])->assertSessionHasErrors('is_default_for_appointments');

    expect($workspace['unit']->fresh()->appointment_sales_automation_enabled)->toBeFalse()
        ->and($workspace['unit']->fresh()->appointment_default_sale_category_id)->toBeNull();
});

it('preserves the appointment item id and synchronizes its automatic sale item by source id', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();
    $appointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 10:00'),
    );
    $appointmentItem = $appointment->items()->firstOrFail();
    $sale = Sale::query()->firstOrFail();
    $saleItem = SaleItem::query()->firstOrFail();
    $saleItemId = $saleItem->getKey();
    $newService = Service::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'name' => 'Serviço atualizado',
        'duration_minutes' => 60,
        'price_cents' => 18000,
    ]);
    $newProfessional = Professional::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);
    $newProfessional->services()->attach($newService, [
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);
    AvailabilityRule::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'professional_id' => $newProfessional->getKey(),
        'weekday' => 0,
    ]);

    $updatedAppointment = app(UpdateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        appointmentSaleAutomationPayload($workspace['customer'], $newProfessional, $newService, '2030-02-10 10:00') + ['lock_version' => 0],
    );
    $updatedItem = $updatedAppointment->items()->firstOrFail();
    $saleItem->refresh();
    $sale->refresh();

    expect($updatedItem->getKey())->toBe($appointmentItem->getKey())
        ->and($updatedItem->service_id)->toBe($newService->getKey())
        ->and($saleItem->getKey())->toBe($saleItemId)
        ->and($saleItem->service_id)->toBe($newService->getKey())
        ->and($saleItem->professional_id)->toBe($newProfessional->getKey())
        ->and($saleItem->name_snapshot)->toBe('Serviço atualizado')
        ->and($saleItem->unit_price_cents)->toBe(18000)
        ->and($saleItem->total_cents)->toBe(18000)
        ->and($sale->total_amount_cents)->toBe(18000)
        ->and($sale->final_amount_cents)->toBe(18000);
});

it('matches automatic sale items by appointment item metadata without changing manual items', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();
    $appointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 10:00'),
    );
    $appointmentItem = $appointment->items()->firstOrFail();
    $sale = Sale::query()->firstOrFail();
    $automaticItem = SaleItem::query()->firstOrFail();
    $automaticItem->forceFill([
        'source_id' => null,
        'source_metadata' => [
            'origin' => 'appointment_automation',
            'created_automatically' => true,
            'appointment_item_id' => $appointmentItem->getKey(),
        ],
    ])->save();
    $manualItem = SaleItem::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Item manual',
        'unit_price_cents' => 9900,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 9900,
    ]);
    $newService = Service::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'name' => 'Serviço via metadata',
        'duration_minutes' => 60,
        'price_cents' => 17000,
    ]);
    $workspace['professional']->services()->attach($newService, [
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);

    app(UpdateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $newService, '2030-02-10 10:00') + ['lock_version' => 0],
    );
    $automaticItem->refresh();
    $manualItem->refresh();

    expect($automaticItem->service_id)->toBe($newService->getKey())
        ->and($automaticItem->name_snapshot)->toBe('Serviço via metadata')
        ->and($automaticItem->unit_price_cents)->toBe(17000)
        ->and($automaticItem->total_cents)->toBe(17000)
        ->and($manualItem->name_snapshot)->toBe('Item manual')
        ->and($manualItem->unit_price_cents)->toBe(9900)
        ->and($manualItem->total_cents)->toBe(9900);
});

it('does not synchronize sale items when the linked sale is finalized', function () {
    $workspace = appointmentSaleAutomationWorkspace();
    $workspace['unit']->update([
        'appointment_sales_automation_enabled' => true,
        'appointment_default_sale_category_id' => $workspace['category']->getKey(),
    ]);
    $workspace['context'] = $workspace['context']->revalidate();
    $appointment = app(CreateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $workspace['service'], '2030-02-10 10:00'),
    );
    $appointmentItem = $appointment->items()->firstOrFail();
    $sale = Sale::query()->firstOrFail();
    $saleItem = SaleItem::query()->firstOrFail();
    $sale->update(['status' => 'finalized']);
    $newService = Service::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'name' => 'Serviço após fechamento',
        'duration_minutes' => 60,
        'price_cents' => 19000,
    ]);
    $workspace['professional']->services()->attach($newService, [
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);

    app(UpdateAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        appointmentSaleAutomationPayload($workspace['customer'], $workspace['professional'], $newService, '2030-02-10 10:00') + ['lock_version' => 0],
    );
    $appointmentItem->refresh();
    $saleItem->refresh();

    expect($appointmentItem->service_id)->toBe($newService->getKey())
        ->and($saleItem->service_id)->toBe($workspace['service']->getKey())
        ->and($saleItem->name_snapshot)->toBe($workspace['service']->name)
        ->and($saleItem->unit_price_cents)->toBe(12500)
        ->and($saleItem->total_cents)->toBe(12500);
});

it('cancels an open automatic sale without manual items when its appointment is cancelled', function () {
    ['workspace' => $workspace, 'appointment' => $appointment, 'sale' => $sale] = appointmentSaleAutomationCancellationFixture();

    app(CancelAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'],
    );

    $sale->refresh();

    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($sale->status)->toBe('cancelled')
        ->and($sale->statusHistories()->where('to_status', 'cancelled')->count())->toBe(1);
});

it('preserves the automatic sale when it contains a manual item', function () {
    ['workspace' => $workspace, 'appointment' => $appointment, 'sale' => $sale] = appointmentSaleAutomationCancellationFixture();

    SaleItem::query()->create([
        'id' => (string) Str::uuid7(),
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'sale_id' => $sale->getKey(),
        'item_type' => 'custom',
        'name_snapshot' => 'Item adicionado manualmente',
        'unit_price_cents' => 2500,
        'quantity' => 1,
        'discount_cents' => 0,
        'total_cents' => 2500,
    ]);

    app(CancelAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'],
    );

    expect($sale->fresh()->status)->toBe('open')
        ->and($sale->statusHistories()->where('to_status', 'cancelled')->count())->toBe(0);
});

it('preserves the automatic sale when it belongs to a closing session', function () {
    ['workspace' => $workspace, 'appointment' => $appointment, 'sale' => $sale] = appointmentSaleAutomationCancellationFixture();

    $closingSession = ClosingSession::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
    ]);
    $closingSession->sales()->attach($sale->getKey());

    app(CancelAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'],
    );

    expect($sale->fresh()->status)->toBe('open')
        ->and($sale->statusHistories()->where('to_status', 'cancelled')->count())->toBe(0);
});

it('preserves the automatic sale when it has a payment cash movement', function () {
    ['workspace' => $workspace, 'appointment' => $appointment, 'sale' => $sale] = appointmentSaleAutomationCancellationFixture();

    $cashShift = CashShift::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'opened_by_user_id' => $workspace['owner']->getKey(),
    ]);
    CashMovement::factory()->create([
        'tenant_id' => $workspace['tenant']->getKey(),
        'unit_id' => $workspace['unit']->getKey(),
        'cash_shift_id' => $cashShift->getKey(),
        'type' => 'sale_inflow',
        'amount_cents' => 12500,
        'reason' => 'Pagamento da comanda',
        'reference_type' => 'sale',
        'reference_id' => $sale->getKey(),
        'user_id' => $workspace['owner']->getKey(),
    ]);

    app(CancelAppointment::class)->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'],
    );

    expect($sale->fresh()->status)->toBe('open')
        ->and($sale->statusHistories()->where('to_status', 'cancelled')->count())->toBe(0);
});

it('does not repeat cancellation side effects when an appointment is already cancelled', function () {
    ['workspace' => $workspace, 'appointment' => $appointment, 'sale' => $sale] = appointmentSaleAutomationCancellationFixture();
    $action = app(CancelAppointment::class);

    $action->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => 0, 'cancel_reason' => 'Cliente solicitou cancelamento'],
    );
    $appointment->refresh();
    $sale->refresh();
    $appointmentHistoryCount = $appointment->statusHistories()->count();
    $saleHistoryCount = $sale->statusHistories()->count();
    $saleLockVersion = $sale->lock_version;

    $action->handle(
        $workspace['owner'],
        $workspace['context'],
        $appointment,
        ['lock_version' => $appointment->lock_version, 'cancel_reason' => 'Reenvio da solicitação'],
    );

    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($appointment->fresh()->statusHistories()->count())->toBe($appointmentHistoryCount)
        ->and($sale->fresh()->status)->toBe('cancelled')
        ->and($sale->fresh()->statusHistories()->count())->toBe($saleHistoryCount)
        ->and($sale->fresh()->lock_version)->toBe($saleLockVersion);
});
