<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
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
        $onlineBookingCount = Appointment::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('source', 'online')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereIn('status', ['scheduled', 'confirmed'])
                            ->where('starts_at', '>=', now());
                    })
                    ->orWhereIn('status', ['checked_in', 'in_service']);
            })
            ->count();

        return Inertia::render('billing/index', [
            'subscription' => [
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at === null ? null : CarbonImmutable::parse($subscription->starts_at)->toISOString(),
                'ends_at' => $subscription->ends_at === null ? null : CarbonImmutable::parse($subscription->ends_at)->toISOString(),
                'grace_ends_at' => $subscription->grace_ends_at === null ? null : CarbonImmutable::parse($subscription->grace_ends_at)->toISOString(),
                'plan' => ['name' => $subscription->plan?->name, 'price_cents' => $subscription->plan?->price_cents],
            ],
            'onlineBookingCount' => $onlineBookingCount,
            'checkoutUrl' => config('services.lastlink.checkout_url'),
        ]);
    }
}
