<?php

namespace App\Actions\Sales;

use App\Actions\Operational\OperationalAction;
use App\Models\Sale;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApplySaleDiscount extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Sale $sale, array $data): Sale
    {
        $unit = $context->unit;

        if ($unit === null || ! $context->user->is($actor) || ! $this->authorization->can($actor, $context, 'sale.discount', $unit)) {
            throw new AuthorizationException('O operador não possui permissão para conceder descontos em comandas.');
        }

        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        $this->assertOwnSale($context, $sale);

        return DB::transaction(function () use ($actor, $context, $sale, $data): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && $lockedSale->lock_version !== (int) $data['lock_version']) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            if (! in_array($lockedSale->status, ['draft', 'open', 'ready_to_bill'], true)) {
                throw ValidationException::withMessages([
                    'sale' => 'Apenas comandas em aberto ou em rascunho podem receber desconto.',
                ]);
            }

            $discountAmountCents = (int) ($data['discount_amount_cents'] ?? 0);

            if ($discountAmountCents < 0) {
                throw ValidationException::withMessages([
                    'discount_amount_cents' => 'O valor do desconto não pode ser negativo.',
                ]);
            }

            if ($discountAmountCents > $lockedSale->total_amount_cents) {
                throw ValidationException::withMessages([
                    'discount_amount_cents' => 'O desconto não pode ser maior que o total da comanda.',
                ]);
            }

            $finalAmountCents = max(0, $lockedSale->total_amount_cents - $discountAmountCents);
            $notes = isset($data['notes']) ? (string) $data['notes'] : $lockedSale->notes;

            $lockedSale->forceFill([
                'discount_amount_cents' => $discountAmountCents,
                'final_amount_cents' => $finalAmountCents,
                'notes' => $notes,
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'sale.discount_applied', $lockedSale, [
                'discount_amount_cents' => $discountAmountCents,
                'final_amount_cents' => $finalAmountCents,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $lockedSale;
        }, 5);
    }
}
