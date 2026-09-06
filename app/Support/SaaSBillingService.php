<?php

namespace App\Support;

use App\Enums\EntitlementStatus;
use App\Models\BillingWebhookEvent;
use App\Models\Entitlement;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantBillingAccount;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaaSBillingService
{
    public function ensureFreeTier(Tenant $tenant): TenantSubscription
    {
        return DB::transaction(function () use ($tenant): TenantSubscription {
            $plan = PlatformPlan::query()->firstOrCreate(['key' => 'free'], ['name' => 'Free', 'trial_days' => 14, 'features' => [], 'limits' => []]);
            $subscription = TenantSubscription::query()->where('tenant_id', $tenant->getKey())->latest()->first();
            if ($subscription !== null) {
                return $subscription;
            }
            $subscription = TenantSubscription::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $tenant->getKey(), 'platform_plan_id' => $plan->getKey(), 'status' => 'trial', 'starts_at' => now(), 'ends_at' => now()->addDays(max(1, $plan->trial_days))]);
            Entitlement::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $tenant->getKey(), 'key' => 'saas.access', 'status' => EntitlementStatus::Trial, 'starts_at' => $subscription->starts_at, 'ends_at' => $subscription->ends_at, 'source' => 'trial', 'config' => []]);

            return $subscription;
        }, 5);
    }

    /** @param array<string, mixed> $payload */
    public function processLastlink(array $payload): BillingWebhookEvent
    {
        $eventId = trim((string) ($payload['Id'] ?? ''));
        $eventName = trim((string) ($payload['Event'] ?? ''));
        if ($eventId === '' || $eventName === '') {
            throw new \InvalidArgumentException('Lastlink webhook requires Id and Event.');
        }
        $event = BillingWebhookEvent::query()->firstOrCreate(['provider' => 'lastlink', 'provider_event_id' => $eventId], ['id' => (string) Str::uuid7(), 'event' => $eventName, 'payload' => $payload, 'received_at' => now()]);
        if ($event->processed_at !== null) {
            return $event;
        }

        return DB::transaction(function () use ($event, $payload, $eventName): BillingWebhookEvent {
            $event = BillingWebhookEvent::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $event->increment('attempts');
            $data = (array) ($payload['Data'] ?? []);
            $email = strtolower(trim((string) data_get($data, 'Buyer.Email')));
            $subId = data_get($data, 'Subscriptions.0.Id') ?? data_get($data, 'Subscription.Id');
            $account = $email === '' ? null : TenantBillingAccount::query()->whereRaw('lower(email) = ?', [$email])->first();
            if ($account === null && $email !== '') {
                $user = User::query()->where('email_normalized', $email)->first();
                $tenantId = $user?->memberships()->where('status', 'active')->value('tenant_id');
                if ($tenantId !== null) {
                    $account = TenantBillingAccount::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'provider' => 'lastlink', 'external_buyer_id' => data_get($data, 'Buyer.Id'), 'email' => $email]);
                }
            }
            $subscription = $subId ? TenantSubscription::query()->where('provider', 'lastlink')->where('external_subscription_id', $subId)->first() : null;
            if ($subscription === null && $account !== null) {
                $subscription = TenantSubscription::query()->where('tenant_id', $account->tenant_id)->latest()->first();
            }
            if ($subscription !== null) {
                $status = match ($eventName) {
                    'Purchase_Order_Confirmed', 'Product_Access_Started', 'Recurrent_Payment' => 'active', 'Subscription_Renewal_Pending' => 'grace', 'Subscription_Canceled', 'Product_Access_Ended', 'Payment_Refund', 'Payment_Chargeback' => 'suspended', 'Subscription_Expired', 'Purchase_Request_Expired' => 'expired', default => $subscription->status
                };
                $graceEndsAt = $eventName === 'Subscription_Renewal_Pending' ? now()->addDays(3) : null;
                $subscription->update(['status' => $status, 'external_subscription_id' => $subId ?: $subscription->external_subscription_id, 'last_payment_at' => $eventName === 'Recurrent_Payment' ? now() : $subscription->last_payment_at, 'grace_ends_at' => $graceEndsAt]);
                $entitlementStatus = $status === 'active' ? 'active' : ($status === 'grace' ? 'grace' : ($status === 'expired' ? 'expired' : 'suspended'));
                Entitlement::query()->where('tenant_id', $subscription->tenant_id)->where('key', 'saas.access')->update(['status' => $entitlementStatus, 'ends_at' => $status === 'active' ? null : ($graceEndsAt ?? now()->addDays(3))]);
            }
            $event->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null]);

            return $event;
        }, 5);
    }

    public function expireDue(): int
    {
        $count = 0;
        TenantSubscription::query()->whereIn('status', ['trial', 'active', 'grace'])->where(function ($q): void {
            $q->whereNotNull('ends_at')->where('ends_at', '<=', now())->orWhere('status', 'grace')->whereNotNull('grace_ends_at')->where('grace_ends_at', '<=', now());
        })->each(function (TenantSubscription $subscription) use (&$count): void {
            $subscription->update(['status' => 'expired']);
            Entitlement::query()->where('tenant_id', $subscription->tenant_id)->where('key', 'saas.access')->update(['status' => 'expired', 'ends_at' => now()->subSecond()]);
            $count++;
        });

        return $count;
    }
}
