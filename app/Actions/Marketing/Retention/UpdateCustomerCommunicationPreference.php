<?php

namespace App\Actions\Marketing\Retention;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateCustomerCommunicationPreference extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Customer $customer, array $data): CustomerCommunicationPreference
    {
        $unit = $this->unit($actor, $context, 'retention.manage');
        if ($customer->tenant_id !== $context->tenant->getKey() || $customer->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The customer belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $customer, $unit, $data): CustomerCommunicationPreference {
            Customer::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $optedIn = (bool) $data['opted_in'];
            $preference = CustomerCommunicationPreference::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->where('customer_id', $customer->getKey())
                ->where('channel', $data['channel'])
                ->lockForUpdate()
                ->first();
            $attributes = ['opted_in' => $optedIn, 'source' => $data['source'] ?? 'staff', 'consented_at' => $optedIn ? now() : ($preference?->consented_at), 'revoked_at' => $optedIn ? null : now(), 'lock_version' => ($preference?->lock_version ?? 0) + 1];
            if ($preference === null) {
                $preference = CustomerCommunicationPreference::query()->create(['id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'customer_id' => $customer->getKey(), 'channel' => $data['channel'], ...$attributes]);
            } else {
                $preference->forceFill($attributes)->save();
            }
            $this->events->record($actor, $context, 'customer.communication_preference.updated', $preference, ['customer_id' => $customer->getKey(), 'channel' => $preference->channel, 'opted_in' => $preference->opted_in]);

            return $preference->fresh();
        }, 5);
    }
}
