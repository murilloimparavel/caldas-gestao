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
                ->has('userName')
                ->has('period')
                ->has('topKpis')
                ->has('visitsTrend')
                ->has('statusBreakdown')
                ->has('professionalPerformance')
                ->has('professionalOccupancy')
                ->has('salesCategoryBreakdown')
                ->has('scheduleHeatmap')
                ->has('appointments')
                ->has('attentionItems')
            )
        );
});
