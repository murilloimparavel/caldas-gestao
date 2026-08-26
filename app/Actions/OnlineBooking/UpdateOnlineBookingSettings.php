<?php

namespace App\Actions\OnlineBooking;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateOnlineBookingSettings extends OperationalAction
{
    /** @param array{online_booking_enabled:bool,service_ids:list<string>,professional_ids:list<string>,lock_version:int} $data */
    public function handle(User $actor, TenantContext $context, array $data): Unit
    {
        $unit = $this->unit($actor, $context, 'unit.update');
        $serviceIds = array_values(array_unique($data['service_ids']));
        $professionalIds = array_values(array_unique($data['professional_ids']));

        return DB::transaction(function () use ($actor, $context, $unit, $data, $serviceIds, $professionalIds): Unit {
            $lockedUnit = Unit::query()
                ->whereKey($unit->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->lock_version !== $data['lock_version']) {
                throw new ConflictHttpException('As configurações de agendamento foram modificadas concorrentemente.');
            }

            $services = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->whereIn('id', $serviceIds)
                ->lockForUpdate()
                ->get();
            $professionals = Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->whereIn('id', $professionalIds)
                ->lockForUpdate()
                ->get();

            if ($services->count() !== count($serviceIds) || $professionals->count() !== count($professionalIds)) {
                throw new AuthorizationException('Os itens selecionados não pertencem à unidade ativa.');
            }

            $allActiveServices = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $allActiveServices->each(function (Service $service) use ($serviceIds): void {
                $service->forceFill([
                    'online_booking_enabled' => in_array($service->getKey(), $serviceIds, true),
                    'lock_version' => $service->lock_version + 1,
                ])->save();
            });

            $allActiveProfessionals = Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $lockedUnit->getKey())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            $allActiveProfessionals->each(function (Professional $professional) use ($professionalIds): void {
                $professional->forceFill([
                    'online_booking_enabled' => in_array($professional->getKey(), $professionalIds, true),
                    'lock_version' => $professional->lock_version + 1,
                ])->save();
            });

            $lockedUnit->forceFill([
                'online_booking_enabled' => $data['online_booking_enabled'],
                'lock_version' => $lockedUnit->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'unit.online_booking_updated', $lockedUnit, [
                'service_ids' => $serviceIds,
                'professional_ids' => $professionalIds,
                'lock_version' => $lockedUnit->lock_version,
            ]);

            return $lockedUnit->fresh();
        }, 5);
    }
}
