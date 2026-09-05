<?php

namespace App\Actions\Marketing\Subscriptions;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SubscribeCustomer extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): CustomerSubscription
    {
        $unit = $this->unit($actor, $context, 'subscription.subscribe');

        $customerId = (string) ($data['customer_id'] ?? '');
        $planId = (string) ($data['subscription_plan_id'] ?? '');

        $customer = Customer::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($customerId)
            ->first();

        if ($customer === null) {
            throw new InvalidArgumentException('The selected customer was not found in this unit.');
        }

        $plan = SubscriptionPlan::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereKey($planId)
            ->first();

        if ($plan === null) {
            throw new InvalidArgumentException('The selected subscription plan was not found in this unit.');
        }

        if (! $plan->is_active) {
            throw new InvalidArgumentException('The selected subscription plan is not active.');
        }

        $startDate = Carbon::today();
        $nextBillingDate = $this->calculateNextBillingDate($startDate, $plan->billing_cycle);

        return DB::transaction(function () use ($actor, $context, $unit, $customer, $plan, $startDate, $nextBillingDate, $data): CustomerSubscription {
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            $hasCurrent = CustomerSubscription::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_id', $customer->getKey())
                ->whereIn('status', ['active', 'paused'])
                ->exists();

            if ($hasCurrent) {
                throw ValidationException::withMessages(['subscription_plan_id' => 'O cliente já possui uma assinatura ativa ou pausada nesta unidade.']);
            }

            $subscription = CustomerSubscription::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_id' => $customer->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'price_cents' => $plan->price_cents,
                'billing_cycle' => $plan->billing_cycle,
                'status' => 'active',
                'start_date' => $startDate->toDateString(),
                'next_billing_date' => $nextBillingDate->toDateString(),
                'notes' => $data['notes'] ?? null,
                'lock_version' => 0,
            ]);

            (new EnsureSubscriptionCycle)->handle($actor, $context, $subscription, permission: 'subscription.subscribe');

            $this->events->record($actor, $context, 'customer_subscription.created', $subscription, [
                'customer_id' => $customer->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => $plan->billing_cycle,
                'price_cents' => $plan->price_cents,
                'start_date' => $startDate->toDateString(),
                'next_billing_date' => $nextBillingDate->toDateString(),
                'status' => 'active',
            ]);

            return $subscription->load(['plan.services', 'customer']);
        }, 5);
    }

    private function calculateNextBillingDate(Carbon $startDate, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => $startDate->copy()->addMonth(),
            'quarterly' => $startDate->copy()->addMonths(3),
            'yearly' => $startDate->copy()->addYear(),
            default => $startDate->copy()->addMonth(),
        };
    }
}
