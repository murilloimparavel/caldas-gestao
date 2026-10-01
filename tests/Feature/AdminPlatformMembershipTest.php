<?php

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('counts only active memberships in platform tenant summaries', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();
    Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'status' => 'active']);
    Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'status' => 'invited']);
    Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'status' => 'revoked', 'revoked_at' => now()]);

    $this->actingAs($admin)->withoutVite()->get(route('admin.tenants'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/tenants')
            ->where('tenants.data.0.id', $tenant->getKey())
            ->where('tenants.data.0.memberships_count', 1)
            ->has('tenants.data.0.memberships', 1));

    $this->actingAs($admin)->withoutVite()->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/dashboard')
            ->where('recentTenants.0.id', $tenant->getKey())
            ->where('recentTenants.0.memberships_count', 1)
            ->has('recentTenants.0.memberships', 1));

    $this->actingAs($admin)->withoutVite()->get(route('admin.tenants.show', $tenant))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('platform/clients/show')
            ->where('client.members', 1)
            ->where('client.subscription.seats', 1)
            ->has('users', 3)
            ->where('users', function (Collection $users): bool {
                return $users->pluck('status')->sort()->values()->all() === ['active', 'invited', 'revoked'];
            }));
});
