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

final class SettleFinancialObligation extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, FinancialObligation $obligation, array $data, ?int $expectedVersion = null): FinancialObligation
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'financial.settle');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($obligation->tenant_id !== $tenantId || $obligation->unit_id !== $unitId) {
            throw new AuthorizationException('Esta obrigação financeira pertence a outra unidade ou workspace.');
        }

        if ($expectedVersion === null) {
            throw new ConflictHttpException('O lock_version da obrigação financeira é obrigatório para esta operação.');
        }

        return DB::transaction(function () use ($actor, $context, $obligation, $data, $tenantId, $unitId, $expectedVersion): FinancialObligation {
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
                    'status' => 'Esta obrigação financeira já foi liquidada.',
                ]);
            }

            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível liquidar uma obrigação financeira que foi cancelada.',
                ]);
            }

            $paidDate = ! empty($data['paid_date']) ? $data['paid_date'] : now()->toDateString();
            $paymentMethod = ! empty($data['payment_method']) ? trim((string) $data['payment_method']) : 'cash';

            $locked->forceFill([
                'status' => 'paid',
                'paid_date' => $paidDate,
                'payment_method' => $paymentMethod,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'financial_obligation.settled', $locked, [
                'type' => $locked->type,
                'status' => $locked->status,
                'amount_cents' => $locked->amount_cents,
                'paid_date' => $locked->paid_date ? $locked->paid_date->toDateString() : (string) $paidDate,
                'payment_method' => $locked->payment_method,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load(['category', 'supplier', 'customer']);
        }, 5);
    }
}
