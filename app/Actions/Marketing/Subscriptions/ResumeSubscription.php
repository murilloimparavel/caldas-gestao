<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerSubscription;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ResumeSubscription extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, CustomerSubscription $subscription, ?int $expectedVersion = null): CustomerSubscription
    {
        $unit = $this->unit($actor, $context, 'subscription.manage');

        if ($subscription->tenant_id !== $context->tenant->getKey() || $subscription->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The subscription belongs to another workspace.');
        }

        $expectedVersion ??= $subscription->lock_version;

        return DB::transaction(function () use ($actor, $context, $subscription, $expectedVersion): CustomerSubscription {
            $locked = CustomerSubscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The subscription was modified concurrently.');
            }

            if ($locked->status === 'active') {
                return $locked->load(['plan', 'customer']);
            }

            if ($locked->status !== 'paused') {
                throw ValidationException::withMessages(['subscription' => 'Somente assinaturas pausadas podem ser retomadas.']);
            }

            $plan = $locked->plan;
            $newNextBillingDate = match ($locked->billing_cycle) {
                'monthly' => now()->addMonth()->toDateString(),
                'quarterly' => now()->addMonths(3)->toDateString(),
                'yearly' => now()->addYear()->toDateString(),
                default => now()->addMonth()->toDateString(),
            };

            $locked->forceFill([
                'status' => 'active',
                'next_billing_date' => $newNextBillingDate,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'customer_subscription.resumed', $locked, [
                'customer_id' => $locked->customer_id,
                'subscription_plan_id' => $locked->subscription_plan_id,
                'next_billing_date' => $newNextBillingDate,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load(['plan', 'customer']);
        }, 5);
    }
}
