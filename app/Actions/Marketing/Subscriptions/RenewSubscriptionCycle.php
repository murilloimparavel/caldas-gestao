<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionRenewalAttempt;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RenewSubscriptionCycle extends OperationalAction
{
    public function handle(
        User $actor,
        TenantContext $context,
        CustomerSubscription $subscription,
        ?CarbonImmutable $asOf = null,
        ?EnsureSubscriptionCycle $ensureCycle = null,
        int $maxPeriods = 12,
    ): ?SubscriptionCycle {
        $unit = $this->unit($actor, $context, 'subscription.renew');

        if ($subscription->tenant_id !== $context->tenant->getKey() || $subscription->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription belongs to another workspace.');
        }

        $asOf ??= CarbonImmutable::today();
        $ensureCycle ??= new EnsureSubscriptionCycle;
        $current = null;

        for ($period = 0; $period < max(1, $maxPeriods); $period++) {
            try {
                /** @var array{0: SubscriptionCycle|null, 1: bool} $result */
                $result = DB::transaction(function () use ($actor, $context, $subscription, $asOf, $ensureCycle): array {
                    $locked = CustomerSubscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

                    if ($locked->status !== 'active') {
                        return [null, true];
                    }

                    $current = $locked->cycles()->where('status', 'open')->latest('cycle_number')->lockForUpdate()->first();
                    if ($current === null) {
                        $current = $ensureCycle->handle($actor, $context, $locked, permission: 'subscription.renew');
                    }

                    if ($current->ends_on->toImmutable()->greaterThanOrEqualTo($asOf)) {
                        return [$current, true];
                    }

                    $key = sprintf('%s:%s', $locked->getKey(), $current->getKey());
                    $attempt = SubscriptionRenewalAttempt::query()
                        ->where('tenant_id', $locked->tenant_id)
                        ->where('customer_subscription_id', $locked->getKey())
                        ->where('idempotency_key', $key)
                        ->lockForUpdate()
                        ->first();

                    if ($attempt?->status === 'succeeded' && $attempt->target_cycle_id !== null) {
                        return [SubscriptionCycle::query()->findOrFail($attempt->target_cycle_id), false];
                    }

                    $attempt ??= SubscriptionRenewalAttempt::query()->create([
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $locked->tenant_id,
                        'unit_id' => $locked->unit_id,
                        'customer_subscription_id' => $locked->getKey(),
                        'source_cycle_id' => $current->getKey(),
                        'idempotency_key' => $key,
                        'status' => 'pending',
                        'attempted_at' => now(),
                    ]);
                    $attempt->forceFill([
                        'status' => 'pending',
                        'attempted_at' => now(),
                        'failure_code' => null,
                        'failure_message' => null,
                        'completed_at' => null,
                    ])->save();

                    $current->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
                    $next = $ensureCycle->handle(
                        $actor,
                        $context,
                        $locked,
                        $current->ends_on->toImmutable()->addDay(),
                        permission: 'subscription.renew',
                    );
                    $locked->forceFill([
                        'next_billing_date' => $next->ends_on->toImmutable()->addDay()->toDateString(),
                        'lock_version' => $locked->lock_version + 1,
                    ])->save();
                    $attempt->forceFill([
                        'target_cycle_id' => $next->getKey(),
                        'status' => 'succeeded',
                        'completed_at' => now(),
                    ])->save();
                    $this->events->record($actor, $context, 'customer_subscription.cycle_renewed', $locked, [
                        'customer_subscription_id' => $locked->getKey(),
                        'status' => 'active',
                        'next_billing_date' => $next->ends_on->toImmutable()->addDay()->toDateString(),
                    ]);

                    return [$next, false];
                }, 5);
            } catch (\Throwable $exception) {
                $this->recordFailure($subscription, $exception);
                throw $exception;
            }

            $current = $result[0];
            if ($result[1] === true) {
                return $current;
            }
        }

        return $current;
    }

    private function recordFailure(CustomerSubscription $subscription, \Throwable $exception): void
    {
        DB::transaction(function () use ($subscription, $exception): void {
            $locked = CustomerSubscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();
            $source = $locked->cycles()->where('status', 'open')->latest('cycle_number')->firstOrFail();
            $key = sprintf('%s:%s', $locked->getKey(), $source->getKey());

            SubscriptionRenewalAttempt::query()->updateOrCreate(
                [
                    'tenant_id' => $locked->tenant_id,
                    'customer_subscription_id' => $locked->getKey(),
                    'idempotency_key' => $key,
                ],
                [
                    'id' => (string) Str::uuid7(),
                    'unit_id' => $locked->unit_id,
                    'source_cycle_id' => $source->getKey(),
                    'status' => 'failed',
                    'failure_code' => class_basename($exception),
                    'failure_message' => $exception->getMessage(),
                    'attempted_at' => now(),
                    'completed_at' => now(),
                ],
            );
        }, 5);
    }
}
