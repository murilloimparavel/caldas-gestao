<?php

namespace App\Actions\Privacy;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\LegalHold;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateLegalHold extends OperationalAction
{
    /** @param array{reason:string, reference?:string|null, resource_type?:string|null, resource_id?:string|null} $data */
    public function handle(User $actor, TenantContext $context, ?Customer $customer, array $data): LegalHold
    {
        $unit = $this->unit($actor, $context, 'retention.legal_hold');

        if ($customer !== null && ($customer->tenant_id !== $context->tenant->getKey() || $customer->unit_id !== $unit->getKey())) {
            throw new AuthorizationException('The customer belongs to another workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $unit, $customer, $data): LegalHold {
            if ($customer !== null) {
                $customer = Customer::query()
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $unit->getKey())
                    ->whereKey($customer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $query = LegalHold::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereNull('released_at')
                ->where('reason', $data['reason']);

            if ($customer !== null) {
                $query->where('customer_id', $customer->getKey());
            } else {
                $query->where('resource_type', $data['resource_type'] ?? null)->where('resource_id', $data['resource_id'] ?? null);
            }

            $existing = $query->latest('placed_at')->first();
            if ($existing instanceof LegalHold) {
                return $existing;
            }

            $hold = LegalHold::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'customer_id' => $customer?->getKey(),
                'resource_type' => $data['resource_type'] ?? ($customer === null ? 'customer' : null),
                'resource_id' => $data['resource_id'] ?? $customer?->getKey(),
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'placed_by_user_id' => $actor->getKey(),
                'placed_at' => now(),
            ]);
            $this->events->record($actor, $context, 'privacy.legal_hold.placed', $hold, ['legal_hold_id' => $hold->getKey(), 'customer_id' => $customer?->getKey()]);

            return $hold;
        }, 5);
    }
}
