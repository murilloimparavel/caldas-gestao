<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\Service;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateSubscriptionPlan extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): SubscriptionPlan
    {
        $unit = $this->unit($actor, $context, 'subscription.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): SubscriptionPlan {
            $serviceIds = $data['service_ids'] ?? [];
            unset($data['service_ids']);

            $plan = SubscriptionPlan::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'lock_version' => 0,
            ]);

            $this->syncServices($plan, $context, $serviceIds);

            $this->events->record($actor, $context, 'subscription_plan.created', $plan, [
                'is_active' => $plan->is_active,
                'price_cents' => $plan->price_cents,
                'billing_cycle' => $plan->billing_cycle,
                'service_ids' => $serviceIds,
            ]);

            return $plan->load('services');
        }, 5);
    }

    /** @param list<string> $serviceIds */
    private function syncServices(SubscriptionPlan $plan, TenantContext $context, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique($serviceIds));

        if (! empty($serviceIds)) {
            $matchingCount = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereIn('id', $serviceIds)
                ->count();

            if (count($serviceIds) !== $matchingCount) {
                throw new InvalidArgumentException('Each service must belong to the active unit.');
            }
        }

        $plan->services()->syncWithPivotValues(
            $serviceIds,
            [
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit?->getKey(),
            ]
        );
    }
}
