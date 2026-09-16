<?php

use App\Actions\Appointments\CreateAppointment;
use App\Actions\Appointments\CreateAppointmentSale;
use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AppointmentSaleLink;
use App\Models\AvailabilityRule;
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
