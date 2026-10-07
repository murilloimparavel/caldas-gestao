<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\CustomerPackage;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelCustomerPackage extends OperationalAction
{
    /** @param array{reason: string} $data */
    public function handle(User $actor, TenantContext $context, CustomerPackage $customerPackage, array $data): CustomerPackage
    {
        $unit = $context->unit;
        if ($unit === null || ! $context->user->is($actor)
            || (! $this->authorization->can($actor, $context, 'package.sell', $unit)
                && ! $this->authorization->can($actor, $context, 'package.manage', $unit))) {
            throw new AuthorizationException('O operador não pode cancelar pacotes pendentes nesta unidade.');
        }

        if ($customerPackage->tenant_id !== $context->tenant->getKey() || $customerPackage->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('O pacote pertence a outra unidade ou workspace.');
        }

        $reason = trim($data['reason']);

        return DB::transaction(function () use ($actor, $context, $unit, $customerPackage, $reason): CustomerPackage {
            /** @var CustomerPackage $package */
            $package = CustomerPackage::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->whereKey($customerPackage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($package->status !== 'pending') {
                throw ValidationException::withMessages([
                    'package' => 'Somente pacotes pendentes podem ser cancelados por esta ação; pacotes pagos devem ser estornados pelo recebimento.',
                ]);
            }

            $hasUsage = PackageUsage::query()->where('customer_package_id', $package->getKey())->exists();
            $hasReservation = PackageUsageReservation::query()
                ->where('customer_package_id', $package->getKey())
                ->whereIn('status', ['reserved', 'consumed'])
                ->exists();
            $hasSaleItem = SaleItem::query()->where('customer_package_id', $package->getKey())->exists();

            if ($hasUsage || $hasReservation || $hasSaleItem) {
                throw ValidationException::withMessages([
                    'package' => 'Remova a linha da comanda e libere as reservas antes de cancelar o pacote.',
                ]);
            }

            $package->forceFill([
                'status' => 'cancelled',
                'lock_version' => $package->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'customer_package.cancelled', $package, [
                'customer_package_id' => $package->getKey(),
                'reason' => $reason,
                'status' => 'cancelled',
            ]);

            return $package;
        }, 5);
    }
}
