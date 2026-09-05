<?php

namespace App\Actions\Marketing\Retention;

use App\Models\Customer;
use App\Models\CustomerRetentionEvent;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordCustomerActivity
{
    public function handle(User $actor, TenantContext $context, ?string $customerId, string $activityType, ?CarbonImmutable $occurredAt = null): ?Customer
    {
        if ($customerId === null) {
            return null;
        }

        $occurredAt ??= CarbonImmutable::now();

        return DB::transaction(function () use ($actor, $context, $customerId, $activityType, $occurredAt): ?Customer {
            $customer = Customer::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereKey($customerId)
                ->lockForUpdate()
                ->first();

            if ($customer === null || $customer->anonymized_at !== null) {
                return $customer;
            }

            $attributes = [];
            if ($customer->last_activity_at === null || $customer->last_activity_at->lt($occurredAt)) {
                $attributes['last_activity_at'] = $occurredAt;
            }

            if ($customer->retention_status === 'at_risk') {
                $attributes['retention_status'] = 'reactivated';
                $attributes['retention_reactivated_at'] = $occurredAt;
                CustomerRetentionEvent::query()->create([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $customer->tenant_id,
                    'unit_id' => $customer->unit_id,
                    'customer_id' => $customer->getKey(),
                    'actor_user_id' => $actor->getKey(),
                    'event_type' => 'reactivated',
                    'metadata' => ['source' => $activityType],
                    'occurred_at' => $occurredAt,
                ]);
            }

            if ($attributes === []) {
                return $customer;
            }

            $attributes['lock_version'] = $customer->lock_version + 1;
            $customer->forceFill($attributes)->save();

            return $customer->fresh();
        }, 5);
    }
}
