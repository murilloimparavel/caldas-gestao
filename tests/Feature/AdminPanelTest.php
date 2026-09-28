<?php

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;

it('only allows super administrators to access the platform dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();

    $user->forceFill(['is_super_admin' => true])->save();
    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

it('creates a tenant, owner membership, and selected subscription from the admin panel', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $plan = PlatformPlan::factory()->create(['trial_days' => 14]);

    $response = $this->actingAs($admin)->post('/admin/tenants', [
        'name' => 'Cliente Novo', 'slug' => 'cliente-novo', 'owner_name' => 'Dono Novo',
        'owner_email' => 'dono@cliente.test', 'owner_password' => 'Senha-Forte-123!', 'plan_id' => $plan->id,
    ]);

    $response->assertRedirect('/admin/tenants');
    $tenant = Tenant::query()->where('slug', 'cliente-novo')->firstOrFail();
    expect($tenant->memberships()->where('status', 'active')->exists())->toBeTrue();
    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->where('platform_plan_id', $plan->id)->exists())->toBeTrue();
});

it('updates a tenant subscription and status', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $plan = PlatformPlan::factory()->create();
    $this->actingAs($admin)->patch("/admin/tenants/{$tenant->id}/subscription", ['plan_id' => $plan->id, 'status' => 'active'])->assertRedirect();
    $this->actingAs($admin)->patch("/admin/tenants/{$tenant->id}/status", ['status' => 'suspended'])->assertRedirect();
    expect($tenant->fresh()->status->value)->toBe('suspended');
    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->value('status'))->toBe('active');
});
