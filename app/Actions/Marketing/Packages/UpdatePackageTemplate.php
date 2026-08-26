<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\PackageTemplate;
use App\Models\Service;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdatePackageTemplate extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, PackageTemplate $template, array $data, ?int $expectedVersion = null): PackageTemplate
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);

        $unit = $this->unit($actor, $context, 'package.manage');

        if ($template->tenant_id !== $context->tenant->getKey() || $template->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The package template belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The package template lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $template, $data, $expectedVersion): PackageTemplate {
            $locked = PackageTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The package template was modified concurrently.');
            }

            $serviceIds = null;
            if (array_key_exists('service_ids', $data)) {
                $serviceIds = is_array($data['service_ids']) ? array_values(array_map('strval', $data['service_ids'])) : [];
                unset($data['service_ids']);
            }

            $locked->forceFill([
                ...$data,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            if (is_array($serviceIds)) {
                $this->syncServices($locked, $context, $serviceIds);
            }

            $this->events->record($actor, $context, 'package_template.updated', $locked, [
                'is_active' => $locked->is_active,
                'price_cents' => $locked->price_cents,
                'total_sessions' => $locked->total_sessions,
                'validity_days' => $locked->validity_days,
                'lock_version' => $locked->lock_version,
                'service_ids' => $serviceIds ?? $locked->services()->pluck('services.id')->all(),
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }

    /** @param list<string> $serviceIds */
    private function syncServices(PackageTemplate $template, TenantContext $context, array $serviceIds): void
    {
        $serviceIds = array_values(array_unique($serviceIds));

        if (! empty($serviceIds)) {
            $matchingCount = Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->whereIn('id', $serviceIds)
                ->count();

            if (count($serviceIds) !== $matchingCount) {
                throw new InvalidArgumentException('Each service must belong to the active unit.');
            }
        }

        $template->services()->syncWithPivotValues(
            $serviceIds,
            [
                'tenant_id' => $context->tenant->getKey(),
                'unit_id' => $context->unit?->getKey(),
            ]
        );
    }
}
