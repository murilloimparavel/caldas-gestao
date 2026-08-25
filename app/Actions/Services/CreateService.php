<?php

namespace App\Actions\Services;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateService extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Service
    {
        $unit = $this->unit($actor, $context, 'service.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Service {
            $professionalIds = $data['professional_ids'] ?? [];
            unset($data['professional_ids']);
            $service = Service::query()->create([...$data, 'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'lock_version' => 0]);
            $this->syncProfessionals($service, $context, $professionalIds);
            $this->events->record($actor, $context, 'service.created', $service, [
                'status' => $service->status,
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
                'professional_ids' => $professionalIds,
            ]);

            return $service->load('professionals');
        }, 5);
    }

    /** @param list<string> $professionalIds */
    private function syncProfessionals(Service $service, TenantContext $context, array $professionalIds): void
    {
        $professionalIds = array_values(array_unique($professionalIds));
        if (count($professionalIds) !== Professional::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $professionalIds)->count()) {
            throw new \InvalidArgumentException('Each professional must belong to the active unit.');
        }
        $service->professionals()->syncWithPivotValues($professionalIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
    }
}
