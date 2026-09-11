<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
use App\Models\FinancialObligation;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\SaleItem;
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
        'status' => 'completed',
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
                ->where('topKpis.appointments.value', '2')
                ->where('topKpis.tickets.value', '1')
                ->where('topKpis.tickets.conversionRate', 50)
                ->has('visitsTrend')
                ->has('statusBreakdown')
                ->has('professionalPerformance', 1, fn (Assert $prof) => $prof
                    ->where('name', 'Dr. Ana Silva')
                    ->where('totalServices', 2)
                    ->where('averageTicket', 'R$ 75,00')
                    ->etc()
                )
                ->has('salesCategoryBreakdown')
                ->has('scheduleHeatmap')
                ->has('appointments', 1)
                ->has('attentionItems', 1)
                ->etc()
            )
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
