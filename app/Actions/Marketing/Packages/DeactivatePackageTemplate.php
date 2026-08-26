<?php

namespace App\Actions\Marketing\Packages;

use App\Actions\Operational\OperationalAction;
use App\Models\PackageTemplate;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeactivatePackageTemplate extends OperationalAction
{
    public function handle(User $actor, TenantContext $context, PackageTemplate $template, ?int $expectedVersion = null): PackageTemplate
    {
        $unit = $this->unit($actor, $context, 'package.manage');

        if ($template->tenant_id !== $context->tenant->getKey() || $template->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The package template belongs to another workspace.');
        }

        $expectedVersion ??= $template->lock_version;

        return DB::transaction(function () use ($actor, $context, $template, $expectedVersion): PackageTemplate {
            $locked = PackageTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The package template was modified concurrently.');
            }

            if (! $locked->is_active) {
                return $locked->load('services');
            }

            $locked->forceFill([
                'is_active' => false,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'package_template.deactivated', $locked, [
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load('services');
        }, 5);
    }
}
