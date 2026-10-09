<?php

namespace App\Actions\Customers;

use App\Actions\Operational\OperationalAction;
use App\Models\Customer;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateCustomer extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Customer $customer, array $data, ?int $expectedVersion = null): Customer
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        if (array_key_exists('phone', $data)) {
            $data['phone_normalized'] = $this->normalizePhone($data['phone']);
        }
        $unit = $this->unit($actor, $context, 'customer.manage');
        if ($customer->tenant_id !== $context->tenant->getKey() || $customer->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The customer belongs to another workspace.');
        }
        if ($expectedVersion === null) {
            throw new ConflictHttpException('The customer lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $customer, $data, $expectedVersion): Customer {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The customer was modified concurrently.');
            }
            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();
            $this->events->record($actor, $context, 'customer.updated', $locked, ['status' => $locked->status, 'lock_version' => $locked->lock_version]);

            return $locked->fresh();
        }, 5);
    }

    private function normalizePhone(mixed $phone): ?string
    {
        $digits = is_string($phone) ? preg_replace('/\D+/', '', $phone) : null;

        return $digits === '' ? null : $digits;
    }
}
