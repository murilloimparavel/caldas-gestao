<?php

namespace App\Actions\Finance\Transactions;

use App\Actions\Operational\OperationalAction;
use App\Models\FinancialObligation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelFinancialObligation extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, FinancialObligation $obligation, array $data = [], ?int $expectedVersion = null): FinancialObligation
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        $unit = $this->unit($actor, $context, 'financial.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($obligation->tenant_id !== $tenantId || $obligation->unit_id !== $unitId) {
            throw new AuthorizationException('Esta obrigação financeira pertence a outra unidade ou workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('O lock_version da obrigação financeira é obrigatório para esta operação.');
        }

        return DB::transaction(function () use ($actor, $context, $obligation, $tenantId, $unitId, $expectedVersion): FinancialObligation {
            /** @var FinancialObligation $locked */
            $locked = FinancialObligation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->lock_version !== $expectedVersion) {
                throw new ConflictHttpException("A obrigação financeira #{$locked->getKey()} foi modificada concorrentemente.");
            }

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível cancelar uma obrigação financeira que já foi liquidada.',
                ]);
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Esta obrigação financeira já se encontra cancelada.',
                ]);
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'financial_obligation.cancelled', $locked, [
                'type' => $locked->type,
                'status' => $locked->status,
                'amount_cents' => $locked->amount_cents,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load(['category', 'supplier', 'customer']);
        }, 5);
    }
}
