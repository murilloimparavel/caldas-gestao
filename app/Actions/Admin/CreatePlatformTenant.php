<?php

namespace App\Actions\Admin;

use App\Actions\Identity\OnboardTenant;
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
                'temporary_password_expires_at' => now()->addDays(3), 'email_verified_at' => now(),
            ]);
            $tenant = $this->onboard->handle($owner, ['name' => $data['name'], 'slug' => $data['slug'] ?? $data['name']], ['name' => $data['name']]);
            if (! empty($data['plan_id'])) {
                $plan = PlatformPlan::query()->findOrFail($data['plan_id']);
                $subscription = TenantSubscription::query()
                    ->where('tenant_id', $tenant->getKey())
                    ->lockForUpdate()
                    ->first();
                $attributes = [
                    'tenant_id' => $tenant->getKey(), 'platform_plan_id' => $plan->getKey(), 'status' => 'trial',
                    'starts_at' => now(), 'ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                    'metadata' => ['created_by' => 'admin_panel'],
                ];
                if ($subscription === null) {
                    TenantSubscription::query()->create($attributes);
                } else {
                    $subscription->update($attributes);
                }
            }
            return $tenant->fresh(['memberships.user', 'units']);
        }, 5);
    }
}
