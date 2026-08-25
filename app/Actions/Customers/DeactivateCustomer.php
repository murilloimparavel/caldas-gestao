<?php

namespace App\Actions\Customers;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivateCustomer extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, Customer $customer, ?int $expectedVersion = null): Customer
    {
        $unit = $this->unit($actor, $context, 'customer.manage');
        if ($customer->tenant_id !== $context->tenant->getKey() || $customer->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The customer belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The customer lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $customer, $expectedVersion): Customer {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The customer was modified concurrently.');
            }
            if ($locked->status === 'inactive') {
                return $locked;
            }
            $locked->forceFill(['status' => 'inactive', 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'customer.deactivated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh();
        }, 5);
    }
}
