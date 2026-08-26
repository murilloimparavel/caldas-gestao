<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('serves operational dashboard snapshot payload', function () {
    $owner = User::factory()->create();
    (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard', fn (Assert $dash) => $dash
                ->has('period')
                ->has('total_sales_cents')
                ->has('today_sales_cents')
                ->has('sales_variation_percentage')
                ->has('total_appointments')
                ->has('growth_rate_percentage')
                ->has('total_sales_count')
                ->has('conversion_rate_percentage')
                ->has('visits_trend')
                ->has('status_breakdown')
                ->has('ticket_medio')
                ->has('professionals_performance')
                ->has('sales_by_category')
                ->has('appointment_funnel')
                ->has('schedule_heatmap')
            )
        );
});
