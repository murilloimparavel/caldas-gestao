<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\User;
use Illuminate\Support\Str;

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('allows authenticated users to visit the dashboard', function () {
    $owner = User::factory()->create();
    (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSuccessful();
});
