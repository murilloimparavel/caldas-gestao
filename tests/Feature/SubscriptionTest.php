<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function subscriptionTestWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Workspace '.Str::random(8),
        'slug' => 'workspace-'.Str::lower(Str::random(8)),
    ]);

    return [$owner, $tenant, $tenant->units()->firstOrFail()];
}

it('subscribes a customer with immutable price and billing snapshots', function () {
    [$owner, $tenant, $unit] = subscriptionTestWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $plan = SubscriptionPlan::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'price_cents' => 12900,
        'billing_cycle' => 'quarterly',
        'is_active' => true,
    ]);

    $response = $this->actingAs($owner)->post(route('customer-subscriptions.store'), [
        'customer_id' => $customer->id,
        'subscription_plan_id' => $plan->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('customers.show', $customer));
    $subscription = CustomerSubscription::query()->firstOrFail();
    expect($subscription->price_cents)->toBe(12900)
        ->and($subscription->billing_cycle)->toBe('quarterly');

    $plan->update(['price_cents' => 19900, 'billing_cycle' => 'yearly']);
    expect($subscription->fresh()->price_cents)->toBe(12900)
        ->and($subscription->fresh()->billing_cycle)->toBe('quarterly');
});

it('rejects a second active or paused subscription for the same customer and unit', function () {
    [$owner, $tenant, $unit] = subscriptionTestWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $firstPlan = SubscriptionPlan::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $secondPlan = SubscriptionPlan::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);

    $this->actingAs($owner)->post(route('customer-subscriptions.store'), [
        'customer_id' => $customer->id,
        'subscription_plan_id' => $firstPlan->id,
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($owner)->post(route('customer-subscriptions.store'), [
        'customer_id' => $customer->id,
        'subscription_plan_id' => $secondPlan->id,
    ]);

    $response->assertRedirect()->assertSessionHasErrors('subscription_plan_id');
    expect(CustomerSubscription::query()->where('customer_id', $customer->id)->count())->toBe(1);
});

it('supports pause resume and cancel while rejecting invalid transitions', function () {
    [$owner, $tenant, $unit] = subscriptionTestWorkspace();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $plan = SubscriptionPlan::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $this->actingAs($owner)->post(route('customer-subscriptions.store'), ['customer_id' => $customer->id, 'subscription_plan_id' => $plan->id]);
    $subscription = CustomerSubscription::query()->firstOrFail();

    $this->actingAs($owner)->post(route('customer-subscriptions.pause', $subscription), ['lock_version' => 0])->assertRedirect();
    expect($subscription->fresh()->status)->toBe('paused');
    $this->actingAs($owner)->post(route('customer-subscriptions.resume', $subscription), ['lock_version' => 1])->assertRedirect();
    expect($subscription->fresh()->status)->toBe('active');
    $this->actingAs($owner)->post(route('customer-subscriptions.cancel', $subscription), ['lock_version' => 2])->assertRedirect();
    expect($subscription->fresh()->status)->toBe('cancelled');
    $this->actingAs($owner)->post(route('customer-subscriptions.pause', $subscription), ['lock_version' => 3])
        ->assertRedirect()->assertSessionHasErrors('subscription');
});

it('scopes subscription plan search and customer profile options to the active unit', function () {
    [$owner, $tenant, $unit] = subscriptionTestWorkspace();
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->id]);
    SubscriptionPlan::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id, 'name' => 'Plano visível']);
    SubscriptionPlan::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $otherUnit->id, 'name' => 'Plano oculto']);

    $this->actingAs($owner)->get(route('subscriptions.index', ['search' => 'Plano']))
        ->assertInertia(fn ($page) => $page->has('plans.data', 1)->where('plans.data.0.name', 'Plano visível'));
});

it('requires authentication to view subscription plans', function () {
    subscriptionTestWorkspace();

    $this->get(route('subscriptions.index'))->assertRedirect();
});
