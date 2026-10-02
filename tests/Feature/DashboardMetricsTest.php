<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\FinancialObligation;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function dashboardTestWorkspace(): array
{
    $owner = User::factory()->create(['name' => 'João Dutra']);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('calculates dashboard metrics with existing sales and appointments in camelCase format', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner, $tenant, $unit] = dashboardTestWorkspace();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Dr. Ana Silva',
    ]);

    // Current period appointments (last 30 days default)
    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-25 10:00:00'),
        'ends_at' => Carbon::parse('2026-08-25 11:00:00'),
        'status' => 'confirmed',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 14:00:00'),
        'ends_at' => Carbon::parse('2026-08-26 15:00:00'),
        'status' => 'checked_in',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-23 10:00:00'),
        'ends_at' => Carbon::parse('2026-08-23 12:00:00'),
        'status' => 'confirmed',
    ]);

    // Completed sale in current period
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'finalized',
        'final_amount_cents' => 15000,
        'created_at' => Carbon::parse('2026-08-26 14:30:00'),
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_id' => $sale->id,
        'professional_id' => $professional->id,
        'item_type' => 'service',
        'total_cents' => 15000,
    ]);

    SaleItem::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_id' => $sale->id,
        'item_type' => 'product',
        'total_cents' => 5000,
    ]);

    // Overdue financial obligation to trigger attention item
    FinancialObligation::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'status' => 'pending',
        'due_date' => Carbon::parse('2026-08-20'),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard', fn (Assert $dash) => $dash
                ->where('userName', 'João Dutra')
                ->where('topKpis.totalSales.value', 'R$ 150,00')
                ->where('topKpis.totalSales.todayValue', 'R$ 150,00')
                ->where('topKpis.appointments.value', '3')
                ->where('topKpis.tickets.value', '1')
                ->where('topKpis.tickets.conversionRate', 33.3)
                ->has('visitsTrend')
                ->has('statusBreakdown')
                ->has('professionalPerformance', 1, fn (Assert $prof) => $prof
                    ->where('name', 'Dr. Ana Silva')
                    ->where('totalServices', 3)
                    ->where('averageTicket', 'R$ 50,00')
                    ->etc()
                )
                ->where('professionalOccupancy.overallPercentage', null)
                ->where('professionalOccupancy.professionals.0.bookedMinutes', 240)
                ->where('professionalOccupancy.professionals.0.occupancyPercentage', null)
                ->has('salesCategoryBreakdown')
                ->where('salesCategoryBreakdown.0.totalAmount', 'R$ 112,50')
                ->where('salesCategoryBreakdown.1.totalAmount', 'R$ 37,50')
                ->where('salesCategoryBreakdown.0.percentage', 75)
                ->where('salesCategoryBreakdown.1.percentage', 25)
                ->where('scheduleHeatmap.74.count', 1)
                ->where('scheduleHeatmap.75.count', 1)
                ->has('appointments', 1)
                ->has('attentionItems', 1)
                ->etc()
            )
        );
});

it('calculates occupancy against merged availability and subtracts overlapping blocks', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner, $tenant, $unit] = dashboardTestWorkspace();
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Ana Silva',
    ]);

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'weekday' => 3,
        'starts_at' => '09:00:00',
        'ends_at' => '17:00:00',
        'timezone' => 'America/Sao_Paulo',
    ]);

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'weekday' => 3,
        'starts_at' => '12:00:00',
        'ends_at' => '17:00:00',
        'timezone' => 'America/Sao_Paulo',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 10:00:00'),
        'ends_at' => Carbon::parse('2026-08-26 12:00:00'),
        'status' => 'confirmed',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 11:00:00'),
        'ends_at' => Carbon::parse('2026-08-26 12:00:00'),
        // PostgreSQL rejects overlapping active appointments. Keep this
        // fixture inactive so the assertions stay focused on availability
        // and overlapping schedule blocks.
        'status' => 'cancelled',
        'cancelled_at' => Carbon::parse('2026-08-26 10:30:00'),
        'cancel_reason' => 'Teste de disponibilidade',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 13:00:00'),
        'ends_at' => Carbon::parse('2026-08-26 14:00:00'),
        'status' => 'cancelled',
        'cancelled_at' => Carbon::parse('2026-08-26 12:30:00'),
        'cancel_reason' => 'Cliente cancelou o horário',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 14:00:00'),
        'ends_at' => Carbon::parse('2026-08-26 15:00:00'),
        'status' => 'no_show',
    ]);

    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 12:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-08-26 13:00:00', 'UTC'),
    ]);

    ScheduleBlock::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => null,
        'starts_at' => Carbon::parse('2026-08-26 12:30:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-08-26 14:00:00', 'UTC'),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => 'today']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.professionalOccupancy.bookedMinutes', 120)
            ->where('dashboard.professionalOccupancy.availableMinutes', 360)
            ->where('dashboard.professionalOccupancy.overallPercentage', 33.3)
            ->where('dashboard.professionalOccupancy.professionals.0.occupancyPercentage', 33.3)
        );
});

it('uses each availability rule timezone and counts adjacent appointments once', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner, $tenant, $unit] = dashboardTestWorkspace();
    $tenant->forceFill(['timezone' => 'America/Sao_Paulo'])->save();
    $unit->forceFill(['timezone' => 'America/Sao_Paulo'])->save();

    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'name' => 'Ana Silva',
    ]);

    AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'weekday' => 3,
        'starts_at' => '12:00:00',
        'ends_at' => '16:00:00',
        'timezone' => 'UTC',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 09:00:00', 'America/Sao_Paulo'),
        'ends_at' => Carbon::parse('2026-08-26 10:00:00', 'America/Sao_Paulo'),
        'status' => 'confirmed',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 10:00:00', 'America/Sao_Paulo'),
        'ends_at' => Carbon::parse('2026-08-26 11:00:00', 'America/Sao_Paulo'),
        'status' => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => 'today']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.professionalOccupancy.bookedMinutes', 120)
            ->where('dashboard.professionalOccupancy.availableMinutes', 240)
            ->where('dashboard.professionalOccupancy.overallPercentage', 50)
        );
});

it('responds to preset date filters correctly', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner, $tenant, $unit] = dashboardTestWorkspace();

    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);

    // Sale today
    Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'finalized',
        'final_amount_cents' => 5000,
        'created_at' => Carbon::parse('2026-08-26 09:00:00'),
    ]);

    // Sale 5 days ago
    Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'finalized',
        'final_amount_cents' => 8000,
        'created_at' => Carbon::parse('2026-08-21 15:00:00'),
    ]);

    // Test 'today' preset filter
    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => 'today']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'today')
            ->where('dashboard.topKpis.totalSales.value', 'R$ 50,00')
        );

    // Test '7d' preset filter
    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => '7d']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', '7d')
            ->where('dashboard.topKpis.totalSales.value', 'R$ 130,00')
        );

    // Test 'custom' preset filter
    $this->actingAs($owner)
        ->get(route('dashboard', [
            'preset' => 'custom',
            'start_date' => '2026-08-26',
            'end_date' => '2026-08-26',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'custom')
            ->where('dashboard.topKpis.totalSales.value', 'R$ 50,00')
        );
});

it('resolves custom date boundaries in the units timezone', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner, $tenant, $unit] = dashboardTestWorkspace();
    $unit->update(['timezone' => 'America/New_York']);

    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);

    Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'finalized',
        'final_amount_cents' => 5000,
        // 03:59 UTC is 23:59 on 25/08 in New York (the unit's local day).
        'created_at' => Carbon::parse('2026-08-26 03:59:00', 'UTC'),
    ]);

    Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'finalized',
        'final_amount_cents' => 8000,
        // 04:01 UTC is 00:01 on 26/08 in New York and must be excluded.
        'created_at' => Carbon::parse('2026-08-26 04:01:00', 'UTC'),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard', [
            'preset' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.period.startDate', '2026-08-25')
            ->where('dashboard.period.endDate', '2026-08-25')
            ->where('dashboard.topKpis.totalSales.value', 'R$ 50,00')
        );
});

it('accepts 366 custom dates and rejects 367 custom dates', function () {
    Carbon::setTestNow('2026-08-26 12:00:00');

    [$owner] = dashboardTestWorkspace();

    $this->actingAs($owner)
        ->get(route('dashboard', [
            'preset' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-01',
        ]))
        ->assertSuccessful();

    $this->actingAs($owner)
        ->get(route('dashboard', [
            'preset' => 'custom',
            'start_date' => '2025-01-01',
            'end_date' => '2026-01-02',
        ]))
        ->assertSessionHasErrors('end_date');
});
