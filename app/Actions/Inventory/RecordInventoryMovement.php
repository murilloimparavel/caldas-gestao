<?php

namespace App\Actions\Inventory;

use App\Actions\Operational\OperationalAction;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RecordInventoryMovement extends OperationalAction
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, TenantContext $context, Product $product, array $data): InventoryMovement
    {
        $unit = $this->unit($actor, $context, 'inventory.manage');
        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $product, $data, $tenantId, $unitId): InventoryMovement {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== $lockedProduct->lock_version) {
                throw new ConflictHttpException("O produto #{$lockedProduct->getKey()} foi modificado concorrentemente.");
            }

            $type = (string) ($data['type'] ?? '');
            $validTypes = ['sale_outflow', 'purchase_inflow', 'adjustment_loss', 'adjustment_gain', 'manual_count'];
            if (! in_array($type, $validTypes, true)) {
                throw ValidationException::withMessages([
                    'type' => 'O tipo de movimentação de estoque é inválido.',
                ]);
            }

            $quantity = (int) ($data['quantity'] ?? 0);
            if ($type === 'manual_count') {
                if ($quantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'A quantidade na contagem de estoque não pode ser negativa.',
                    ]);
                }
            } else {
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'A quantidade movimentada deve ser maior que zero.',
                    ]);
                }
            }

            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'O motivo da movimentação é obrigatório.',
                ]);
            }

            $previousStock = $lockedProduct->current_stock;
            $resultingStock = match ($type) {
                'purchase_inflow', 'adjustment_gain' => $previousStock + $quantity,
                'sale_outflow', 'adjustment_loss' => $previousStock - $quantity,
                'manual_count' => $quantity,
            };

            $unitCostCents = isset($data['unit_cost_cents'])
                ? (int) $data['unit_cost_cents']
                : $lockedProduct->cost_price_cents;

            /** @var InventoryMovement $movement */
            $movement = InventoryMovement::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'product_id' => $lockedProduct->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost_cents' => $unitCostCents,
                'previous_stock' => $previousStock,
                'resulting_stock' => $resultingStock,
                'reason' => $reason,
                'reference_type' => isset($data['reference_type']) && trim((string) $data['reference_type']) !== '' ? trim((string) $data['reference_type']) : null,
                'reference_id' => isset($data['reference_id']) && trim((string) $data['reference_id']) !== '' ? trim((string) $data['reference_id']) : null,
                'user_id' => $actor->getKey(),
            ]);

            $lockedProduct->forceFill([
                'current_stock' => $resultingStock,
                'lock_version' => $lockedProduct->lock_version + 1,
            ])->save();

            $this->events->record($actor, $context, 'inventory.moved', $lockedProduct, [
                'product_id' => $lockedProduct->getKey(),
                'inventory_movement_id' => $movement->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'resulting_stock' => $resultingStock,
                'lock_version' => $lockedProduct->lock_version,
            ]);

            return $movement;
        }, 5);
    }
}
