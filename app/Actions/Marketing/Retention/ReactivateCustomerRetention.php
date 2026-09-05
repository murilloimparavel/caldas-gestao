<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\CustomerRetentionEvent;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReactivateCustomerRetention extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Customer $customer): Customer
    {
        $unit = $this->unit($actor, $context, 'retention.manage');
        if ($customer->tenant_id !== $context->tenant->getKey() || $customer->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The customer belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $customer): Customer {
            $locked = Customer::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->retention_status !== 'reactivated') {
                $locked->forceFill(['retention_status' => 'reactivated', 'retention_reactivated_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
                CustomerRetentionEvent::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $locked->unit_id, 'customer_id' => $locked->getKey(), 'actor_user_id' => $actor->getKey(), 'event_type' => 'reactivated', 'metadata' => [], 'occurred_at' => now()]);
                $this->events->record($actor, $context, 'customer.retention.reactivated', $locked, ['customer_id' => $locked->getKey()]);
            }

            return $locked->fresh();
        }, 5);
    }
}
