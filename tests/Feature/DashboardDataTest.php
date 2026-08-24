<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('serves an empty dashboard contract until operational sources are connected', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.mode', 'empty')
            ->has('dashboard.metrics', 0)
            ->has('dashboard.appointments', 0)
            ->has('dashboard.attentionItems', 0));
});
