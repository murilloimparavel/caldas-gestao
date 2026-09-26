<?php

namespace App\Actions\SaleCategories;

use App\Actions\Operational\OperationalAction;
use App\Models\SaleCategory;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateSaleCategory extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, SaleCategory $saleCategory, array $data, ?int $expectedVersion = null): SaleCategory
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $isDefaultForAppointments = array_key_exists('is_default_for_appointments', $data)
            ? (bool) $data['is_default_for_appointments']
            : null;
        $appointmentAutomationEnabled = array_key_exists('appointment_automation_enabled', $data)
            ? (bool) $data['appointment_automation_enabled']
            : null;
        unset($data['is_default_for_appointments'], $data['appointment_automation_enabled']);
        $unit = $this->unit($actor, $context, 'sale_category.manage');

        if ($saleCategory->tenant_id !== $context->tenant->getKey() || $saleCategory->unit_id !== $unit->getKey()) {
            throw new AuthorizationException('The sale category belongs to another workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('The sale category lock_version is required for this mutation.');
        }

        return DB::transaction(function () use ($actor, $context, $saleCategory, $data, $expectedVersion, $unit, $isDefaultForAppointments, $appointmentAutomationEnabled): SaleCategory {
            $lockedUnit = Unit::query()
                ->whereKey($unit->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $locked = SaleCategory::query()->whereKey($saleCategory->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('The sale category was modified concurrently.');
            }

            $nextType = (string) ($data['type'] ?? $locked->type);
            $defaultCategoryId = (string) ($lockedUnit->appointment_default_sale_category_id ?? '');
            $isCurrentDefault = $defaultCategoryId === (string) $locked->getKey();

            if (($isDefaultForAppointments === true || $isCurrentDefault) && (! $locked->is_active || $nextType === 'product')) {
                throw ValidationException::withMessages([
                    'is_default_for_appointments' => 'A categoria padrão da automação deve estar ativa e aceitar serviços.',
                ]);
            }

            $locked->forceFill([...$data, 'lock_version' => $locked->lock_version + 1])->save();

            if ($isDefaultForAppointments === true) {
                $lockedUnit->forceFill([
                    'appointment_sales_automation_enabled' => true,
                    'appointment_default_sale_category_id' => $locked->getKey(),
                ])->save();
            } elseif ($isDefaultForAppointments === false && $isCurrentDefault) {
                $lockedUnit->forceFill([
                    'appointment_sales_automation_enabled' => false,
                    'appointment_default_sale_category_id' => null,
                ])->save();
            } elseif ($appointmentAutomationEnabled !== null) {
                if ($appointmentAutomationEnabled && $defaultCategoryId === '') {
                    throw ValidationException::withMessages([
                        'appointment_automation_enabled' => 'Selecione uma categoria padrão antes de ativar a automação.',
                    ]);
                }

                $lockedUnit->forceFill([
                    'appointment_sales_automation_enabled' => $appointmentAutomationEnabled,
                ])->save();
            }

            $this->events->record($actor, $context, 'sale_category.updated', $locked, [
                'type' => $locked->type,
                'uniqueness_scope' => $locked->uniqueness_scope,
                'is_active' => $locked->is_active,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh();
        }, 5);
    }
}
