<?php

namespace App\Actions\Finance\Transactions;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UpdateFinancialObligation extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, FinancialObligation $obligation, array $data, ?int $expectedVersion = null): FinancialObligation
    {
        $expectedVersion ??= isset($data['lock_version']) ? (int) $data['lock_version'] : null;
        unset($data['lock_version']);
        $unit = $this->unit($actor, $context, 'financial.manage');
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

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Apenas obrigações financeiras pendentes podem ser editadas.',
                ]);
            }

            if (array_key_exists('category_id', $data) && ! empty($data['category_id'])) {
                $categoryExists = Category::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($data['category_id'])
                    ->exists();

                if (! $categoryExists) {
                    throw ValidationException::withMessages([
                        'category_id' => 'A categoria selecionada não pertence a esta unidade.',
                    ]);
                }
            }

            if (array_key_exists('supplier_id', $data) && ! empty($data['supplier_id'])) {
                $supplierExists = Supplier::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($data['supplier_id'])
                    ->exists();

                if (! $supplierExists) {
                    throw ValidationException::withMessages([
                        'supplier_id' => 'O fornecedor selecionado não pertence a este tenant.',
                    ]);
                }
            }

            if (array_key_exists('customer_id', $data) && ! empty($data['customer_id'])) {
                $customerExists = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($data['customer_id'])
                    ->exists();

                if (! $customerExists) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'O cliente selecionado não pertence a esta unidade.',
                    ]);
                }
            }

            if (isset($data['amount_cents']) && (int) $data['amount_cents'] <= 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'O valor deve ser maior que zero.',
                ]);
            }

            $updatePayload = [];
            if (isset($data['type'])) {
                $updatePayload['type'] = $data['type'];
            }
            if (isset($data['description'])) {
                $updatePayload['description'] = trim((string) $data['description']);
            }
            if (isset($data['amount_cents'])) {
                $updatePayload['amount_cents'] = (int) $data['amount_cents'];
            }
            if (isset($data['due_date'])) {
                $updatePayload['due_date'] = $data['due_date'];
            }
            if (array_key_exists('category_id', $data)) {
                $updatePayload['category_id'] = ! empty($data['category_id']) ? $data['category_id'] : null;
            }
            if (array_key_exists('supplier_id', $data)) {
                $updatePayload['supplier_id'] = ! empty($data['supplier_id']) ? $data['supplier_id'] : null;
            }
            if (array_key_exists('customer_id', $data)) {
                $updatePayload['customer_id'] = ! empty($data['customer_id']) ? $data['customer_id'] : null;
            }
            if (array_key_exists('notes', $data)) {
                $updatePayload['notes'] = isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null;
            }

            $locked->forceFill([
                ...$updatePayload,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'financial_obligation.updated', $locked, [
                'type' => $locked->type,
                'status' => $locked->status,
                'amount_cents' => $locked->amount_cents,
                'due_date' => $locked->due_date->toDateString(),
                'category_id' => $locked->category_id,
                'supplier_id' => $locked->supplier_id,
                'customer_id' => $locked->customer_id,
                'lock_version' => $locked->lock_version,
            ]);

            return $locked->fresh()->load(['category', 'supplier', 'customer']);
        }, 5);
    }
}
