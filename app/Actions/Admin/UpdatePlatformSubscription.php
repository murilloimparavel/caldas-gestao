<?php

namespace App\Actions\Admin;

use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\AuditEventWriter;
use Illuminate\Support\Facades\DB;

final class UpdatePlatformSubscription
{
    public function __construct(private readonly AuditEventWriter $audit = new AuditEventWriter) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Tenant $tenant, array $data): TenantSubscription
    {
        return DB::transaction(function () use ($actor, $tenant, $data): TenantSubscription {
            $plan = PlatformPlan::query()->whereKey($data['plan_id'])->firstOrFail();
            $subscription = TenantSubscription::query()->where('tenant_id', $tenant->getKey())->latest()->lockForUpdate()->first();
            $attributes = [
                'tenant_id' => $tenant->getKey(),
                'platform_plan_id' => $plan->getKey(),
                'status' => $data['status'],
                'billing_cycle' => $data['billing_cycle'] ?? $plan->billing_cycle,
                'starts_at' => $data['starts_at'] ?? ($subscription?->starts_at ?? now()),
                'ends_at' => $data['ends_at'] ?? ($subscription?->ends_at ?? ($data['status'] === 'trial' && $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null)),
                'next_billing_at' => $data['next_billing_at'] ?? $subscription?->next_billing_at,
                'grace_ends_at' => $data['grace_ends_at'] ?? $subscription?->grace_ends_at,
            ];
            $beforeStatus = $subscription?->status;
            $subscription ??= TenantSubscription::query()->create($attributes);
            if ($subscription->exists && $beforeStatus !== null) {
                $subscription->update($attributes);
            }
            $subscription->refresh();

            $entitlement = Entitlement::query()->where('tenant_id', $tenant->getKey())->where('key', 'saas.access')->first();
            $entitlementAttributes = [
                'status' => EntitlementStatus::tryFrom($subscription->status) ?? EntitlementStatus::Expired,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->status === 'active' ? null : ($subscription->grace_ends_at ?? $subscription->ends_at),
                'source' => EntitlementSource::Plan,
                'config' => [],
            ];
            if ($entitlement === null) {
                Entitlement::query()->create(['tenant_id' => $tenant->getKey(), 'key' => 'saas.access', ...$entitlementAttributes]);
            } else {
                $entitlement->update($entitlementAttributes);
            }

            $this->audit->record([
                'actor_user_id' => $actor->getKey(),
                'tenant_id' => $tenant->getKey(),
                'action' => 'platform.subscription.updated',
                'resource_type' => 'tenant_subscription',
                'resource_id' => $subscription->getKey(),
                'metadata' => ['from_status' => $beforeStatus, 'to_status' => $subscription->status, 'subscription_plan_id' => $plan->getKey(), 'billing_cycle' => $subscription->billing_cycle],
            ]);

            return $subscription;
        }, 5);
    }
}
