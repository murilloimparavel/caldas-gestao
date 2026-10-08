<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ArchiveCustomerPackage extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, CustomerPackage $customerPackage): CustomerPackage
    {
        $unit = $this->unit($actor, $context, 'package.manage');

        if ($customerPackage->tenant_id !== $context->tenant->getKey() || $customerPackage->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('O pacote pertence a outra unidade ou workspace.');
        }

        return DB::transaction(function () use ($actor, $context, $unit, $customerPackage): CustomerPackage {
            /** @var CustomerPackage $package */
            $package = CustomerPackage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($customerPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($package->status, ['completed', 'exhausted', 'cancelled', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'package' => 'Somente pacotes concluídos, cancelados ou vencidos podem ser arquivados.',
                ]);
            }

            $previousStatus = $package->status;
            $package->forceFill([
                'status' => 'archived',
                'archived_from_status' => $previousStatus,
                'archived_at' => now(),
                'lock_version' => $package->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'customer_package.archived', $package, [
                'customer_package_id' => $package->getKey(),
                'from_status' => $previousStatus,
                'to_status' => 'archived',
                'status' => 'archived',
            ]);

            return $package;
        }, 5);
    }
}
