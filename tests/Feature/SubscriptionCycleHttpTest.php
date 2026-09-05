<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Subscriptions\EnsureSubscriptionCycle;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\MembershipUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** @return array{0: User, 1: CustomerSubscription, 2: Service} */
function httpSubscriptionWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'HTTP cycles '.Str::random(8),
        'slug' => 'http-cycles-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    $plan = SubscriptionPlan::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'billing_cycle' => 'monthly',
    ]);
    $plan->services()->attach($service, [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'max_uses_per_cycle' => 2,
    ]);
    $subscription = CustomerSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $customer->getKey(),
        'subscription_plan_id' => $plan->getKey(),
        'price_cents' => $plan->price_cents,
        'billing_cycle' => $plan->billing_cycle,
    ]);
    (new EnsureSubscriptionCycle)->handle(
        $owner,
        TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey()),
        $subscription,
        permission: 'subscription.subscribe',
    );

    return [$owner, $subscription, $service];
}

it('consumes subscription usage through JSON with idempotent replay and limit enforcement', function () {
    [$owner, $subscription, $service] = httpSubscriptionWorkspace();

    $payload = ['service_id' => $service->getKey(), 'quantity' => 2];
    $first = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-usage-1')
        ->postJson(route('customer-subscriptions.consume', $subscription), $payload);

    $first->assertSuccessful()->assertJsonPath('entry.quantity', 2);
    $entryId = $first->json('entry.id');
    $cycle = $subscription->cycles()->firstOrFail();
    expect($cycle->usages()->firstOrFail()->fresh()->used_count)->toBe(2);

    $replay = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-usage-1')
        ->postJson(route('customer-subscriptions.consume', $subscription), $payload);

    $replay->assertSuccessful()->assertJsonPath('entry.id', $entryId);
    expect($cycle->usages()->firstOrFail()->fresh()->used_count)->toBe(2);

    $conflict = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-usage-1')
        ->postJson(route('customer-subscriptions.consume', $subscription), [
            'service_id' => $service->getKey(),
            'quantity' => 1,
        ]);

    $conflict->assertStatus(409);

    $limit = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-usage-2')
        ->postJson(route('customer-subscriptions.consume', $subscription), $payload);

    $limit->assertStatus(409);
});

it('rejects subscription usage from another tenant and from a member without usage permission', function () {
    [$owner, $subscription, $service] = httpSubscriptionWorkspace();

    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, [
        'name' => 'Other HTTP '.Str::random(8),
        'slug' => 'other-http-'.Str::lower(Str::random(8)),
    ]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $otherService = Service::factory()->create([
        'tenant_id' => $otherTenant->getKey(),
        'unit_id' => $otherUnit->getKey(),
    ]);

    $this->actingAs($otherOwner)
        ->withHeader('Accept', 'application/json')
        ->postJson(route('customer-subscriptions.consume', $subscription), [
            'service_id' => $otherService->getKey(),
        ])
        ->assertForbidden();

    $reader = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'user_id' => $reader->getKey(),
        'status' => 'active',
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($subscription->unit)->create(['is_primary' => true]);
    $role = Role::factory()->create(['tenant_id' => $subscription->tenant_id, 'key' => 'subscription-reader']);
    $permission = Permission::query()->where('key', 'subscription.view')->firstOrFail();
    RolePermission::query()->create([
        'tenant_id' => $subscription->tenant_id,
        'role_id' => $role->getKey(),
        'permission_id' => $permission->getKey(),
    ]);
    MembershipRole::factory()->forMembership($membership)->forRole($role)->create();

    $this->actingAs($reader)
        ->withHeader('Accept', 'application/json')
        ->postJson(route('customer-subscriptions.consume', $subscription), [
            'service_id' => $service->getKey(),
        ])
        ->assertForbidden();
});

it('renews an overdue cycle through JSON and makes repeated idempotent renewal safe', function () {
    [$owner, $subscription] = httpSubscriptionWorkspace();
    $subscription->cycles()->firstOrFail()->forceFill([
        'starts_on' => CarbonImmutable::today()->subMonth()->toDateString(),
        'ends_on' => CarbonImmutable::today()->subDay()->toDateString(),
    ])->save();

    $first = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-renew-1')
        ->postJson(route('customer-subscriptions.renew', $subscription), [
            'as_of' => CarbonImmutable::today()->toDateString(),
        ]);

    $first->assertSuccessful()->assertJsonPath('cycle.cycle_number', 2);
    $cycleId = $first->json('cycle.id');
    expect($subscription->cycles()->where('status', 'open')->count())->toBe(1);

    $replay = $this->actingAs($owner)
        ->withHeader('Accept', 'application/json')
        ->withHeader('X-Idempotency-Key', 'http-renew-1')
        ->postJson(route('customer-subscriptions.renew', $subscription), [
            'as_of' => CarbonImmutable::today()->toDateString(),
        ]);

    $replay->assertSuccessful()->assertJsonPath('cycle.id', $cycleId);
    expect($subscription->cycles()->count())->toBe(2)
        ->and($subscription->cycles()->where('status', 'open')->count())->toBe(1);
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
