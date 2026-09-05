<?php

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public sales page with default branding', function (): void {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('marketing/home')
            ->where('branding.name', config('branding.name')),
        );
});

it('canonicalizes sign in and sign up aliases', function (): void {
    $this->get('/signin')->assertRedirect(route('login'));
    $this->get('/signup')->assertRedirect(route('register'));
});

it('shares tenant branding with authenticated inertia pages', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $tenant = Tenant::factory()->create([
        'brand_name' => 'Studio Aurora',
        'logo_url' => 'https://cdn.example.com/logo.svg',
        'primary_color' => '#123456',
    ]);
    Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->getKey()])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('workspace.tenant.branding.name', 'Studio Aurora')
            ->where('workspace.tenant.branding.logoUrl', 'https://cdn.example.com/logo.svg')
            ->where('workspace.tenant.branding.primaryColor', '#123456'),
        );
});
