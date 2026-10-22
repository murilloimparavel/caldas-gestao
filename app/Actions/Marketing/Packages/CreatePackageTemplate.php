<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\PackageTemplate;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePackageTemplate extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): PackageTemplate
    {
        $unit = $this->unit($actor, $context, 'package.manage');

        return DB::transaction(function () use ($actor, $context, $data, $unit): PackageTemplate {
            $serviceIds = $data['service_ids'] ?? [];
            $serviceQuantities = $data['service_quantities'] ?? [];
            unset($data['service_ids']);
            unset($data['service_quantities']);

            $template = PackageTemplate::query()->create([
                ...$data,
                'id' => (string) Str::uuid7(),
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'lock_version' => 0,
            ]);

            $this->syncServices($template, $context, $serviceIds, $serviceQuantities);

            $this->events->record($actor, $context, 'package_template.created', $template, [
                'is_active' => $template->is_active,
                'price_cents' => $template->price_cents,
                'total_sessions' => $template->total_sessions,
                'validity_days' => $template->validity_days,
                'service_ids' => $serviceIds,
            ]);

            return $template->load('services');
        }, 5);
    }

    /**
     * @param  list<string>  $serviceIds
     * @param  array<string, int>  $serviceQuantities
     */
    private function syncServices(PackageTemplate $template, TenantContext $context, array $serviceIds, array $serviceQuantities = []): void
    {
        $serviceIds = array_values(array_unique($serviceIds));

        if (! empty($serviceIds)) {
            $matchingCount = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereIn('id', $serviceIds)
                ->count();

            if (count($serviceIds) !== $matchingCount) {
                throw new \InvalidArgumentException('Each service must belong to the active unit.');
            }
        }

        $serviceSync = [];
        foreach ($serviceIds as $serviceId) {
            $quantity = array_key_exists($serviceId, $serviceQuantities)
                ? max(1, (int) $serviceQuantities[$serviceId])
                : (count($serviceIds) === 1 ? (int) $template->total_sessions : 1);

            $serviceSync[$serviceId] = [
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit?->getKey(),
                'included_quantity' => $quantity,
            ];
        }

        if ($serviceSync !== [] && array_sum(array_column($serviceSync, 'included_quantity')) !== (int) $template->total_sessions) {
            throw new \InvalidArgumentException('The sum of service quantities must match the package total sessions.');
        }

        $template->services()->sync($serviceSync);
    }
}
