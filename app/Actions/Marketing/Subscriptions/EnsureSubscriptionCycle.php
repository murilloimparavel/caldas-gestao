<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionCycleUsage;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnsureSubscriptionCycle extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, CustomerSubscription $subscription, ?CarbonImmutable $startsOn = null, ?CarbonImmutable $endsOn = null, string $permission = 'subscription.manage'): SubscriptionCycle
    {
        $unit = $this->unit($actor, $context, $permission);

        if ($subscription->tenant_id !== $context->tenant->getKey() || $subscription->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $subscription, $startsOn, $endsOn): SubscriptionCycle {
            $lockedSubscription = CustomerSubscription::query()
                ->whereKey($subscription->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $open = $lockedSubscription->cycles()
                ->where('status', 'open')
                ->latest('cycle_number')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                return $open->load('usages');
            }

            $latest = $lockedSubscription->cycles()->latest('cycle_number')->lockForUpdate()->first();
            $effectiveStartsOn = $startsOn ?? $latest?->ends_on?->addDay()->toImmutable() ?? CarbonImmutable::parse($lockedSubscription->start_date);
            $effectiveEndsOn = $endsOn ?? ($latest === null
                ? CarbonImmutable::parse($lockedSubscription->next_billing_date ?? $effectiveStartsOn->addMonth())->subDay()
                : $this->cycleEnd($effectiveStartsOn, $lockedSubscription->billing_cycle));
            $number = ($latest?->cycle_number ?? 0) + 1;
            $renewalKey = sprintf('%s:%d:%s', $lockedSubscription->getKey(), $number, $effectiveStartsOn->toDateString());

            $cycle = SubscriptionCycle::query()->firstOrCreate(
                [
                    'tenant_id' => $lockedSubscription->tenant_id,
                    'customer_subscription_id' => $lockedSubscription->getKey(),
                    'renewal_key' => $renewalKey,
                ],
                [
                    'id' => (string) Str::uuid7(),
                    'unit_id' => $lockedSubscription->unit_id,
                    'cycle_number' => $number,
                    'starts_on' => $effectiveStartsOn->toDateString(),
                    'ends_on' => $effectiveEndsOn->toDateString(),
                    'status' => 'open',
                ],
            );

            if ($latest !== null && $latest->next_cycle_id !== $cycle->getKey()) {
                $latest->forceFill(['next_cycle_id' => $cycle->getKey()])->save();
                $cycle->forceFill(['previous_cycle_id' => $latest->getKey()])->save();
            }

            $plan = $lockedSubscription->plan()->with('services')->firstOrFail();
            foreach ($plan->services as $service) {
                $maxUses = $service->pivot->max_uses_per_cycle;
                if ($maxUses !== null && $maxUses < 0) {
                    throw new \LogicException('A subscription plan service cannot have a negative cycle usage limit.');
                }

                SubscriptionCycleUsage::query()->firstOrCreate(
                    [
                        'tenant_id' => $lockedSubscription->tenant_id,
                        'subscription_cycle_id' => $cycle->getKey(),
                        'service_id' => $service->getKey(),
                    ],
                    [
                        'id' => (string) Str::uuid7(),
                        'unit_id' => $lockedSubscription->unit_id,
                        'customer_subscription_id' => $lockedSubscription->getKey(),
                        'max_uses_snapshot' => $maxUses,
                        'used_count' => 0,
                    ],
                );
            }

            $this->events->record($actor, $context, 'customer_subscription.cycle_opened', $lockedSubscription, [
                'customer_subscription_id' => $lockedSubscription->getKey(), 'status' => 'open',
                'start_date' => $cycle->starts_on->toDateString(), 'next_billing_date' => $cycle->ends_on->toDateString(),
            ]);

            return $cycle->fresh(['usages']);
        }, 5);
    }

    private function cycleEnd(CarbonImmutable $startsOn, string $billingCycle): CarbonImmutable
    {
        return match ($billingCycle) {
            'quarterly' => $startsOn->addMonths(3)->subDay(),
            'yearly' => $startsOn->addYear()->subDay(),
            default => $startsOn->addMonth()->subDay(),
        };
    }
}
