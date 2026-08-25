<?php

namespace App\Actions\Professionals;

use App\Actions\Operational\OperationalAction;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProfessional extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): Professional
    {
        $unit = $this->unit($actor, $context, 'professional.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): Professional {
            $serviceIds = $data['service_ids'] ?? [];
            unset($data['service_ids']);
            $professional = Professional::query()->create([...$data, 'id' => (string) Str::uuid7(), 'tenant_id' => $context->tenant->getKey(), 'unit_id' => $unit->getKey(), 'lock_version' => 0]);
            $this->syncServices($professional, $context, $serviceIds);
            $this->events->record($actor, $context, 'professional.created', $professional, [
                'status' => $professional->status,
                'service_ids' => $serviceIds,
            ]);

            return $professional->load('services');
        }, 5);
    }

    /** @param list<string> $serviceIds */
    private function syncServices(Professional $professional, TenantContext $context, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique($serviceIds));
        if (count($serviceIds) !== Service::query()->where('tenant_id', $context->tenant->getKey())->where('unit_id', $context->unit?->getKey())->whereIn('id', $serviceIds)->count()) {
            throw new \InvalidArgumentException('Each service must belong to the active unit.');
        }
        $professional->services()->syncWithPivotValues($serviceIds, ['tenant_id' => $context->tenant->getKey(), 'unit_id' => $context->unit?->getKey()]);
    }
}
