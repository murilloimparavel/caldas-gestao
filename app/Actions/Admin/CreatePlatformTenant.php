<?php

namespace App\Actions\Admin;

use App\Actions\Identity\OnboardTenant;
use App\Enums\EntitlementSource;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreatePlatformTenant
{
    public function __construct(private readonly OnboardTenant $onboard) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $owner = User::query()->create([
                'name' => $data['owner_name'], 'email' => $data['owner_email'],
                'password' => $data['owner_password'], 'must_change_password' => true,
                'temporary_password_expires_at' => now()->addDays(3),
            ]);
            $owner->forceFill(['email_verified_at' => now()])->save();
            $tenant = $this->onboard->handle($owner, ['name' => $data['name'], 'slug' => $data['slug'] ?? $data['name']], ['name' => $data['name']]);
            if (! empty($data['plan_id'])) {
                $plan = PlatformPlan::query()->whereKey((string) $data['plan_id'])->firstOrFail();
                $subscription = TenantSubscription::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->lockForUpdate()
                    ->first();
                $startsAt = now();
                $endsAt = $startsAt->copy()->addDays(max(1, (int) $plan->trial_days));
                $attributes = [
                    'tenant_id' => $tenant->getKey(), 'platform_plan_id' => $plan->getKey(), 'status' => 'trial',
                    'billing_cycle' => $plan->billing_cycle,
                    'starts_at' => $startsAt, 'ends_at' => $endsAt,
                    'metadata' => ['created_by' => 'admin_panel'],
                ];
                if ($subscription === null) {
                    $subscription = TenantSubscription::query()->create($attributes);
                } else {
                    $subscription->update($attributes);
                }
                Entitlement::query()->updateOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'key' => 'saas.access'],
                    [
                        'status' => EntitlementStatus::Trial,
                        'starts_at' => $subscription->starts_at,
                        'ends_at' => $subscription->ends_at,
                        'source' => EntitlementSource::Trial,
                        'config' => [],
                    ],
                );
            }

            return $tenant->fresh(['memberships.user', 'units']);
        }, 5);
    }
}
