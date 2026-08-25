<?php

namespace App\Actions\Customers;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCustomer extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Customer
    {
        $unit = $this->unit($actor, $context, 'customer.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Customer {
            $customer = Customer::query()->create([...$data, 'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'lock_version' => 0]);
            $this->events->record($actor, $context, 'customer.created', $customer, ['status' => $customer->status]);

            return $customer;
        }, 5);
    }
}
