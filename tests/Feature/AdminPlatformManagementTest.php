<?php

use App\Models\AuditEvent;
use App\Models\Entitlement;
use App\Models\Membership;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('manages the platform plan catalogue and records the change', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $response = $this->actingAs($admin)->post('/admin/plans', [
        'key' => 'growth', 'name' => 'Growth', 'price_cents' => 19900,
        'billing_cycle' => 'monthly', 'trial_days' => 14, 'features' => ['reports' => true], 'limits' => ['users' => 10],
    ]);

    $response->assertRedirect(route('admin.plans.index'));
    $plan = PlatformPlan::query()->where('key', 'growth')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.plans.deactivate', $plan))->assertRedirect();
    expect($plan->fresh()->is_active)->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'platform.plan.deactivated')->where('resource_id', $plan->getKey())->exists())->toBeTrue();
});

it('updates subscription dates and keeps the access entitlement synchronized', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $oldPlan = PlatformPlan::factory()->create();
    $newPlan = PlatformPlan::factory()->create(['billing_cycle' => 'yearly']);
    $subscription = TenantSubscription::factory()->create(['tenant_id' => $tenant->getKey(), 'platform_plan_id' => $oldPlan->getKey(), 'status' => 'active']);
    Entitlement::factory()->create(['tenant_id' => $tenant->getKey(), 'key' => 'saas.access']);

    $this->actingAs($admin)->patch(route('admin.tenants.subscription.update', $tenant), [
        'plan_id' => $newPlan->getKey(), 'status' => 'expired', 'starts_at' => now()->subMonth()->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString(),
    ])->assertRedirect();

    $entitlement = Entitlement::query()->where('tenant_id', $tenant->getKey())->where('key', 'saas.access')->firstOrFail();
    expect($subscription->fresh()->platform_plan_id)->toBe($newPlan->getKey())
        ->and($subscription->fresh()->status)->toBe('expired')
        ->and($entitlement->status->value)->toBe('expired')
        ->and(AuditEvent::query()->where('action', 'platform.subscription.updated')->exists())->toBeTrue();
});

it('creates a tenant user, sends a reset invitation, and can revoke the membership', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($admin)->post(route('admin.tenants.users.store', $tenant), [
        'name' => 'Staff Member', 'email' => 'staff@example.test', 'role' => 'staff',
    ])->assertRedirect();

    $user = User::query()->where('email_normalized', 'staff@example.test')->firstOrFail();
    Notification::assertSentTo($user, ResetPassword::class);
    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->firstOrFail();
    $this->actingAs($admin)->post(route('admin.tenants.users.send-access', [$tenant, $membership]))->assertRedirect();
    Notification::assertSentTo($user, ResetPassword::class);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
    $this->actingAs($admin)->delete(route('admin.tenants.memberships.revoke', [$tenant, $membership]))->assertRedirect();

    expect($membership->fresh()->status->value)->toBe('revoked')
        ->and(AuditEvent::query()->where('action', 'platform.user.revoked')->where('resource_id', $membership->getKey())->exists())->toBeTrue();
});

it('publishes real dashboard attention alerts for trial expiry and suspended tenants', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $trialTenant = Tenant::factory()->create(['name' => 'Trial Tenant']);
    $trialPlan = PlatformPlan::factory()->create();
    TenantSubscription::factory()->create([
        'tenant_id' => $trialTenant->getKey(),
        'platform_plan_id' => $trialPlan->getKey(),
        'status' => 'trial',
        'ends_at' => now()->addDays(2),
    ]);
    Tenant::factory()->create(['name' => 'Suspended Tenant', 'status' => 'suspended']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/dashboard')
            ->has('alerts', 4)
            ->where('alerts.0.title', 'Trial Tenant')
            ->where('alerts.1.title', 'Trial Tenant')
            ->where('alerts.2.title', 'Suspended Tenant')
            ->where('alerts.3.title', 'Suspended Tenant')
        );
});

it('publishes a near user limit alert from the active plan limits', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create(['name' => 'Limit Tenant']);
    $plan = PlatformPlan::factory()->create(['limits' => ['users' => 1]]);
    TenantSubscription::factory()->create(['tenant_id' => $tenant->getKey(), 'platform_plan_id' => $plan->getKey(), 'status' => 'active']);
    $user = User::factory()->create();
    Membership::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active', 'joined_at' => now()]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/dashboard')
            ->where('alerts.0.title', 'Limit Tenant')
            ->where('alerts.0.detail', 'Usuários próximos do limite (1/1).')
        );
});

it('allows super administrators to consult the append-only audit stream', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    AuditEvent::factory()->create(['tenant_id' => null, 'actor_user_id' => $admin->getKey(), 'action' => 'platform.audit.test']);

    $this->actingAs($admin)->get(route('admin.audit'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/audit')
            ->has('events.data', 1)
            ->where('events.data.0.action', 'platform.audit.test')
        );
});
