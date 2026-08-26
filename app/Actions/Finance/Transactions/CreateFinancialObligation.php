<?php

namespace App\Actions\Finance\Transactions;

use App\Actions\Operational\OperationalAction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateFinancialObligation extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, array $data): FinancialObligation
    {
        $unit = $this->unit($actor, $context, 'financial.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $data, $tenantId, $unitId): FinancialObligation {
            $type = (string) ($data['type'] ?? '');
            if (! in_array($type, ['payable', 'receivable'], true)) {
                throw ValidationException::withMessages([
                    'type' => 'O tipo de obrigação financeira deve ser "payable" (a pagar) ou "receivable" (a receber).',
                ]);
            }

            $amountCents = (int) ($data['amount_cents'] ?? 0);
            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount_cents' => 'O valor deve ser maior que zero.',
                ]);
            }

            if (! empty($data['category_id'])) {
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

            if (! empty($data['supplier_id'])) {
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

            if (! empty($data['customer_id'])) {
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

            $obligation = FinancialObligation::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'type' => $type,
                'category_id' => ! empty($data['category_id']) ? $data['category_id'] : null,
                'supplier_id' => ! empty($data['supplier_id']) ? $data['supplier_id'] : null,
                'customer_id' => ! empty($data['customer_id']) ? $data['customer_id'] : null,
                'description' => trim((string) ($data['description'] ?? '')),
                'amount_cents' => $amountCents,
                'due_date' => $data['due_date'],
                'paid_date' => null,
                'status' => 'pending',
                'payment_method' => null,
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
                'lock_version' => 1,
            ]);

            $this->events->record($actor, $context, 'financial_obligation.created', $obligation, [
                'type' => $obligation->type,
                'status' => $obligation->status,
                'amount_cents' => $obligation->amount_cents,
                'due_date' => $obligation->due_date->toDateString(),
                'category_id' => $obligation->category_id,
                'supplier_id' => $obligation->supplier_id,
                'customer_id' => $obligation->customer_id,
            ]);

            return $obligation->load(['category', 'supplier', 'customer']);
        }, 5);
    }
}
