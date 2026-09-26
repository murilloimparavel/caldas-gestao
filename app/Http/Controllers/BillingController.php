<?php

namespace App\Http\Controllers;

use App\Support\SaaSBillingService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function __invoke(TenantContext $context, SaaSBillingService $billing): Response
    {
        $subscription = $billing->ensureFreeTier($context->tenant)->load('plan');

        return Inertia::render('billing/index', [
            'subscription' => [
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at === null ? null : CarbonImmutable::parse($subscription->starts_at)->toISOString(),
                'ends_at' => $subscription->ends_at === null ? null : CarbonImmutable::parse($subscription->ends_at)->toISOString(),
                'grace_ends_at' => $subscription->grace_ends_at === null ? null : CarbonImmutable::parse($subscription->grace_ends_at)->toISOString(),
                'plan' => ['name' => $subscription->plan?->name, 'price_cents' => $subscription->plan?->price_cents],
            ],
            'checkoutUrl' => config('services.lastlink.checkout_url'),
        ]);
    }
}
