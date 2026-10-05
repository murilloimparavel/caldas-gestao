<?php

use App\Actions\Admin\CreatePlatformTenant;
use App\Actions\Admin\UpdatePlatformSubscription;
use App\Models\Entitlement;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;

it('creates a bounded trial and access entitlement when a tenant uses a zero-day plan', function (): void {
    $plan = PlatformPlan::factory()->create(['trial_days' => 0]);
    $actor = User::factory()->create();

    $tenant = app(CreatePlatformTenant::class)->handle($actor, [
        'name' => 'Zero Day Trial',
        'slug' => 'zero-day-trial',
        'owner_name' => 'Owner',
        'owner_email' => 'zero-day-owner@example.test',
        'owner_password' => 'password-password',
        'plan_id' => $plan->getKey(),
    ]);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
    $entitlement = Entitlement::query()->where('tenant_id', $tenant->getKey())->where('key', 'saas.access')->firstOrFail();

    expect($subscription->status)->toBe('trial')
        ->and($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at->isFuture())->toBeTrue()
        ->and($entitlement->status->value)->toBe('trial')
        ->and($entitlement->source->value)->toBe('trial')
        ->and($entitlement->ends_at->equalTo($subscription->ends_at))->toBeTrue();
});

it('clears explicitly null subscription dates while preserving omitted dates', function (): void {
    $actor = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $plan = PlatformPlan::factory()->create();
    $startsAt = now()->subMonth();
    $endsAt = now()->addDays(10);
    $nextBillingAt = now()->addDays(30);
    $graceEndsAt = now()->addDays(13);
    $subscription = TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'platform_plan_id' => $plan->getKey(),
        'status' => 'grace',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'next_billing_at' => $nextBillingAt,
        'grace_ends_at' => $graceEndsAt,
    ]);

    app(UpdatePlatformSubscription::class)->handle($actor, $tenant, [
        'plan_id' => $plan->getKey(),
        'status' => 'grace',
    ]);

    $subscription->refresh();
    expect($subscription->ends_at->toDateTimeString())->toBe($endsAt->toDateTimeString())
        ->and($subscription->next_billing_at->toDateTimeString())->toBe($nextBillingAt->toDateTimeString())
        ->and($subscription->grace_ends_at->toDateTimeString())->toBe($graceEndsAt->toDateTimeString());

    app(UpdatePlatformSubscription::class)->handle($actor, $tenant, [
        'plan_id' => $plan->getKey(),
        'status' => 'active',
        'ends_at' => null,
        'next_billing_at' => null,
        'grace_ends_at' => null,
    ]);

    expect($subscription->fresh()->ends_at)->toBeNull()
        ->and($subscription->fresh()->next_billing_at)->toBeNull()
        ->and($subscription->fresh()->grace_ends_at)->toBeNull();
});

it('bounds an edited zero-day trial when its end date is null or omitted', function (): void {
    $actor = User::factory()->create(['is_super_admin' => true]);
    $tenant = Tenant::factory()->create();
    $plan = PlatformPlan::factory()->create(['trial_days' => 0]);
    $subscription = TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'platform_plan_id' => $plan->getKey(),
        'status' => 'active',
        'ends_at' => null,
    ]);

    app(UpdatePlatformSubscription::class)->handle($actor, $tenant, [
        'plan_id' => $plan->getKey(),
        'status' => 'trial',
    ]);

    expect($subscription->fresh()->ends_at)->not->toBeNull()
        ->and($subscription->fresh()->ends_at->isFuture())->toBeTrue();

    app(UpdatePlatformSubscription::class)->handle($actor, $tenant, [
        'plan_id' => $plan->getKey(),
        'status' => 'trial',
        'ends_at' => null,
    ]);

    expect($subscription->fresh()->ends_at)->not->toBeNull()
        ->and($subscription->fresh()->ends_at->isFuture())->toBeTrue();
});
