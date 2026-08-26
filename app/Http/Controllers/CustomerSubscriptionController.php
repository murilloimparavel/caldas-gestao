<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Subscriptions\CancelSubscription;
use App\Actions\Marketing\Subscriptions\PauseSubscription;
use App\Actions\Marketing\Subscriptions\ResumeSubscription;
use App\Actions\Marketing\Subscriptions\SubscribeCustomer;
use App\Http\Requests\CustomerSubscriptionRequest;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

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
        } catch (InvalidArgumentException $exception) {
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
}
