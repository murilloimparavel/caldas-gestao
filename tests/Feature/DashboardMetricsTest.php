<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Appointment;
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
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();

    return [$owner, $tenant, $unit];
}

it('calculates dashboard metrics with existing sales and appointments', function () {
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
        'status' => 'confirmed',
    ]);

    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'professional_id' => $professional->id,
        'starts_at' => Carbon::parse('2026-08-26 14:00:00'),
        'status' => 'completed',
    ]);

    // Completed sale in current period
    $category = SaleCategory::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $sale = Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'completed',
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

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard', fn (Assert $dash) => $dash
                ->where('total_sales_cents', 15000)
                ->where('today_sales_cents', 15000)
                ->where('total_appointments', 2)
                ->where('total_sales_count', 1)
                ->where('conversion_rate_percentage', 50)
                ->where('ticket_medio.current_cents', 15000)
                ->where('appointment_funnel.total', 2)
                ->where('appointment_funnel.confirmed', 2)
                ->where('appointment_funnel.billed', 1)
                ->has('visits_trend')
                ->has('status_breakdown')
                ->has('professionals_performance')
                ->has('sales_by_category')
                ->has('schedule_heatmap')
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
        'status' => 'completed',
        'final_amount_cents' => 5000,
        'created_at' => Carbon::parse('2026-08-26 09:00:00'),
    ]);

    // Sale 5 days ago
    Sale::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'sale_category_id' => $category->id,
        'status' => 'completed',
        'final_amount_cents' => 8000,
        'created_at' => Carbon::parse('2026-08-21 15:00:00'),
    ]);

    // Test 'today' preset filter
    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => 'today']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'today')
            ->where('dashboard.total_sales_cents', 5000)
        );

    // Test '7d' preset filter
    $this->actingAs($owner)
        ->get(route('dashboard', ['preset' => '7d']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', '7d')
            ->where('dashboard.total_sales_cents', 13000)
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
            ->where('dashboard.total_sales_cents', 5000)
        );
});
