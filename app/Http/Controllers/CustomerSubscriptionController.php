<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Subscriptions\CancelSubscription;
use App\Actions\Marketing\Subscriptions\ConsumeSubscriptionUsage;
use App\Actions\Marketing\Subscriptions\PauseSubscription;
use App\Actions\Marketing\Subscriptions\RenewSubscriptionCycle;
use App\Actions\Marketing\Subscriptions\ResumeSubscription;
use App\Actions\Marketing\Subscriptions\SubscribeCustomer;
use App\Http\Requests\ConsumeSubscriptionUsageRequest;
use App\Http\Requests\CustomerSubscriptionRequest;
use App\Http\Requests\RenewSubscriptionCycleRequest;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionUsageEntry;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class CustomerSubscriptionController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function store(CustomerSubscriptionRequest $request, TenantContext $context, SubscribeCustomer $subscribeCustomer): RedirectResponse
    {
        $data = $request->validated();
        try {
            $this->mutation->execute($request, $context, $request->user(), $data, function () use ($subscribeCustomer, $request, $context, $data): array {
                $subscription = $subscribeCustomer->handle($request->user(), $context, $data);

                return ['resource_id' => $subscription->getKey(), 'resource_type' => 'customer_subscription'];
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['subscription_plan_id' => $exception->getMessage()]);
        }

        $customer = Customer::query()->findOrFail($data['customer_id']);

        return to_route('customers.show', $customer)->with('success', 'Assinatura criada com sucesso.');
    }

    public function cancel(CustomerSubscriptionRequest $request, TenantContext $context, CustomerSubscription $customerSubscription, CancelSubscription $cancelSubscription): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($cancelSubscription, $request, $context, $customerSubscription, $data): array {
            $cancelled = $cancelSubscription->handle(
                $request->user(),
                $context,
                $customerSubscription,
                $data,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $cancelled->getKey(), 'resource_type' => 'customer_subscription'];
        });

        return back()->with('success', 'Assinatura cancelada.');
    }

    public function pause(CustomerSubscriptionRequest $request, TenantContext $context, CustomerSubscription $customerSubscription, PauseSubscription $pauseSubscription): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($pauseSubscription, $request, $context, $customerSubscription, $data): array {
            $paused = $pauseSubscription->handle(
                $request->user(),
                $context,
                $customerSubscription,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $paused->getKey(), 'resource_type' => 'customer_subscription'];
        });

        return back()->with('success', 'Assinatura pausada.');
    }

    public function resume(CustomerSubscriptionRequest $request, TenantContext $context, CustomerSubscription $customerSubscription, ResumeSubscription $resumeSubscription): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($resumeSubscription, $request, $context, $customerSubscription, $data): array {
            $resumed = $resumeSubscription->handle(
                $request->user(),
                $context,
                $customerSubscription,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $resumed->getKey(), 'resource_type' => 'customer_subscription'];
        });

        return back()->with('success', 'Assinatura retomada.');
    }

    public function consume(
        ConsumeSubscriptionUsageRequest $request,
        TenantContext $context,
        CustomerSubscription $customerSubscription,
        ConsumeSubscriptionUsage $consumeSubscriptionUsage,
    ): RedirectResponse|JsonResponse {
        $data = $request->validated();
        $data['subscription_id'] = $customerSubscription->getKey();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        if ($idempotencyKey !== '') {
            $data['idempotency_key'] = $idempotencyKey;
        }

        $usageData = [
            'service_id' => (string) $data['service_id'],
            'quantity' => isset($data['quantity']) ? (int) $data['quantity'] : null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'metadata' => is_array($data['metadata'] ?? null) ? $data['metadata'] : null,
        ];
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($consumeSubscriptionUsage, $request, $context, $customerSubscription, $usageData): array {
            $entry = $consumeSubscriptionUsage->handle($request->user(), $context, $customerSubscription, $usageData);

            return ['resource_id' => $entry->getKey(), 'resource_type' => 'subscription_usage_entry'];
        });

        $entry = SubscriptionUsageEntry::query()
            ->with(['service:id,name', 'cycle:id,cycle_number,starts_on,ends_on,status'])
            ->whereKey($reference['resource_id'])
            ->firstOrFail();

        if ($request->wantsJson()) {
            return response()->json([
                'entry' => $entry,
                'subscription' => $customerSubscription->fresh(['plan:id,name,billing_cycle']),
                'cycle' => $entry->cycle,
            ]);
        }

        return back()->with('success', 'Uso da assinatura registrado com sucesso.');
    }

    public function renew(
        RenewSubscriptionCycleRequest $request,
        TenantContext $context,
        CustomerSubscription $customerSubscription,
        RenewSubscriptionCycle $renewSubscriptionCycle,
    ): RedirectResponse|JsonResponse {
        $data = $request->validated();
        $data['subscription_id'] = $customerSubscription->getKey();
        $asOf = CarbonImmutable::parse($data['as_of'] ?? today()->toDateString());
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($renewSubscriptionCycle, $request, $context, $customerSubscription, $asOf): array {
            $cycle = $renewSubscriptionCycle->handle($request->user(), $context, $customerSubscription, $asOf);

            if ($cycle === null) {
                return ['resource_id' => $customerSubscription->getKey(), 'resource_type' => 'customer_subscription'];
            }

            return ['resource_id' => $cycle->getKey(), 'resource_type' => 'subscription_cycle'];
        });

        $cycle = SubscriptionCycle::query()
            ->with(['usages.service:id,name'])
            ->whereKey($reference['resource_id'])
            ->first();

        if ($request->wantsJson()) {
            return response()->json([
                'subscription' => $customerSubscription->fresh(['plan:id,name,billing_cycle']),
                'cycle' => $cycle,
                'renewed' => $cycle?->cycle_number > 1,
            ]);
        }

        return back()->with('success', 'Ciclo da assinatura processado com sucesso.');
    }
}
