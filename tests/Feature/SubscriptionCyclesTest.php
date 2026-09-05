<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Subscriptions\ConsumeSubscriptionUsage;
use App\Actions\Marketing\Subscriptions\EnsureSubscriptionCycle;
use App\Actions\Marketing\Subscriptions\RenewSubscriptionCycle;
use App\Actions\Marketing\Subscriptions\SubscribeCustomer;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalAttempt;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: CustomerSubscription, 4: Service, 5: SubscriptionPlan} */
function subscriptionCyclesWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Cycles '.Str::random(8),
        'slug' => 'cycles-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'unit_id' => $unit->id]);
    $plan = SubscriptionPlan::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'billing_cycle' => 'monthly',
    ]);
    $plan->services()->attach($service, [
        'tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
        'max_uses_per_cycle' => 2,
    ]);
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $subscription = (new SubscribeCustomer)->handle($owner, $context, [
        'customer_id' => $customer->id,
        'subscription_plan_id' => $plan->id,
    ]);

    return [$owner, $tenant, $unit, $subscription, $service, $plan];
}

it('converges cycle creation to one open cycle', function () {
    [$owner, $tenant, $unit, $subscription] = subscriptionCyclesWorkspace();
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $action = new EnsureSubscriptionCycle;

    $first = $action->handle($owner, $context, $subscription);
    $second = $action->handle($owner, $context, $subscription, CarbonImmutable::today()->addMonth());

    expect($first->getKey())->toBe($second->getKey())
        ->and($subscription->cycles()->where('status', 'open')->count())->toBe(1);
});

it('enforces cycle usage limits and rejects conflicting idempotency replays', function () {
    [$owner, $tenant, $unit, $subscription, $service] = subscriptionCyclesWorkspace();
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $action = new ConsumeSubscriptionUsage;
    $data = ['service_id' => $service->id, 'quantity' => 2, 'idempotency_key' => 'usage-1'];

    $entry = $action->handle($owner, $context, $subscription, $data);
    expect($action->handle($owner, $context, $subscription, $data)->getKey())->toBe($entry->getKey());

    expect(fn () => $action->handle($owner, $context, $subscription, [
        ...$data,
        'quantity' => 1,
    ]))->toThrow(ConflictHttpException::class, 'different payload');

    expect(fn () => $action->handle($owner, $context, $subscription, [
        'service_id' => $service->id,
        'quantity' => 1,
        'idempotency_key' => 'usage-2',
    ]))->toThrow(ConflictHttpException::class, 'limit');
});

it('advances all overdue periods up to the safe limit without charging', function () {
    [$owner, $tenant, $unit, $subscription] = subscriptionCyclesWorkspace();
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $cycle = $subscription->cycles()->firstOrFail();
    $cycle->forceFill([
        'starts_on' => CarbonImmutable::today()->subMonths(4)->toDateString(),
        'ends_on' => CarbonImmutable::today()->subMonths(3)->subDay()->toDateString(),
    ])->save();
    $subscription->forceFill(['next_billing_date' => CarbonImmutable::today()->subMonths(3)->toDateString()])->save();

    $result = (new RenewSubscriptionCycle)->handle($owner, $context, $subscription->fresh(), CarbonImmutable::today(), maxPeriods: 6);

    expect($result)->not->toBeNull()
        ->and($subscription->cycles()->count())->toBe(5)
        ->and($subscription->cycles()->where('status', 'open')->count())->toBe(1)
        ->and(SubscriptionRenewalAttempt::query()->where('customer_subscription_id', $subscription->id)->where('status', 'succeeded')->count())->toBe(4);
});

it('persists failed renewal evidence and retries the same period convergently', function () {
    [$owner, $tenant, $unit, $subscription, $service, $plan] = subscriptionCyclesWorkspace();
    $context = TenantContext::forUser($owner, $tenant->id, $unit->id);
    $cycle = $subscription->cycles()->firstOrFail();
    $cycle->forceFill([
        'starts_on' => CarbonImmutable::today()->subMonths(2)->toDateString(),
        'ends_on' => CarbonImmutable::today()->subMonth()->subDay()->toDateString(),
    ])->save();
    $subscription->forceFill(['next_billing_date' => CarbonImmutable::today()->subMonth()->toDateString()])->save();
    $plan->services()->updateExistingPivot($service->id, ['max_uses_per_cycle' => -1]);

    expect(fn () => (new RenewSubscriptionCycle)->handle($owner, $context, $subscription->fresh(), CarbonImmutable::today()))
        ->toThrow(LogicException::class);

    $failed = SubscriptionRenewalAttempt::query()->where('customer_subscription_id', $subscription->id)->firstOrFail();
    expect($failed->status)->toBe('failed')
        ->and($failed->failure_message)->not->toBeNull()
        ->and($cycle->fresh()->status)->toBe('open')
        ->and($subscription->cycles()->count())->toBe(1);

    $plan->services()->updateExistingPivot($service->id, ['max_uses_per_cycle' => 2]);
    $result = (new RenewSubscriptionCycle)->handle($owner, $context, $subscription->fresh(), CarbonImmutable::today());

    expect($result)->not->toBeNull()
        ->and($failed->fresh()->status)->toBe('succeeded')
        ->and($subscription->cycles()->where('status', 'open')->count())->toBe(1);
});

it('does not allow usage across tenant or unit boundaries', function () {
    [$owner, $tenant, $unit, $subscription, $service] = subscriptionCyclesWorkspace();
    $otherOwner = User::factory()->create();
    $otherTenant = (new OnboardTenant)->handle($otherOwner, [
        'name' => 'Other '.Str::random(8),
        'slug' => 'other-'.Str::lower(Str::random(8)),
    ]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $context = TenantContext::forUser($otherOwner, $otherTenant->id, $otherUnit->id);

    expect(fn () => (new ConsumeSubscriptionUsage)->handle($otherOwner, $context, $subscription, [
        'service_id' => $service->id,
    ]))->toThrow(AuthorizationException::class);
});
