<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Actions\Operational\OperationalAction;
use App\Models\CommissionAccrual;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdjustSale extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly RecordInventoryMovement $recordInventoryMovement = new RecordInventoryMovement,
    ) {
        parent::__construct($authorization, $events);
    }

    public function handle(User $actor, TenantContext $context, Sale $sale, string $reason, ?int $expectedVersion = null): Sale
    {
        $unit = $context->unit;

        if ($unit === null || ! $context->user->is($actor) || (! $this->authorization->can($actor, $context, 'sale.adjust', $unit) && ! $this->authorization->can($actor, $context, 'sale.manage', $unit))) {
            throw new AuthorizationException('O operador não possui permissão para estornar comandas.');
        }

        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        if ($sale->tenant_id !== $tenantId || $sale->unit_id !== $unitId) {
            throw new AuthorizationException('A comanda pertence a outra unidade ou workspace.');
        }

        $this->assertOwnSale($context, $sale);

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'O motivo do estorno é obrigatório.',
            ]);
        }

        return DB::transaction(function () use ($actor, $context, $sale, $reason, $expectedVersion, $tenantId, $unitId): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->with('items')
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedVersion !== null && $lockedSale->lock_version !== $expectedVersion) {
                throw new ConflictHttpException('A comanda foi modificada concorrentemente.');
            }

            if ($lockedSale->status !== 'finalized') {
                throw ValidationException::withMessages([
                    'status' => "A comanda possui status '{$lockedSale->status}' e apenas comandas finalizadas podem ser estornadas.",
                ]);
            }

            // 1. Reposição de estoque via RecordInventoryMovement (adjustment_gain)
            foreach ($lockedSale->items as $item) {
                if ($item->item_type === 'product' && $item->product_id !== null) {
                    /** @var Product $product */
                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->whereKey($item->product_id)
                        ->firstOrFail();

                    $this->recordInventoryMovement->handle($actor, $context, $product, [
                        'type' => 'adjustment_gain',
                        'quantity' => $item->quantity,
                        'reason' => 'Estorno compensatório da comanda #'.$lockedSale->getKey().': '.$reason,
                        'reference_type' => 'sale',
                        'reference_id' => $lockedSale->getKey(),
                    ]);
                }
            }

            // 2. Cancelamento de comissões apuradas (accrued -> cancelled)
            /** @var Collection<int, CommissionAccrual> $accruals */
            $accruals = CommissionAccrual::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('sale_id', $lockedSale->getKey())
                ->where('status', 'accrued')
                ->lockForUpdate()
                ->get();

            foreach ($accruals as $accrual) {
                $accrual->forceFill([
                    'status' => 'cancelled',
                    'lock_version' => $accrual->lock_version + 1,
                ])->save();

                $this->events->record($actor, $context, 'commission_accrual.cancelled', $accrual, [
                    'commission_accrual_id' => $accrual->getKey(),
                    'professional_id' => $accrual->professional_id,
                    'sale_id' => $accrual->sale_id,
                    'sale_item_id' => $accrual->sale_item_id,
                    'gross_amount_cents' => $accrual->gross_amount_cents,
                    'rate_type' => $accrual->rate_type,
                    'rate_value' => $accrual->rate_value,
                    'commission_amount_cents' => $accrual->commission_amount_cents,
                    'status' => 'cancelled',
                    'lock_version' => $accrual->lock_version,
                ]);
            }

            // 3. Registro do histórico de transição
            SaleStatusHistory::query()->create([
                'id' => (string) Str::uuid7(),
                'sale_id' => $lockedSale->getKey(),
                'from_status' => 'finalized',
                'to_status' => 'adjusted',
                'user_id' => $actor->getKey(),
                'reason' => $reason,
            ]);

            // 4. Atualização da comanda
            $lockedSale->forceFill([
                'status' => 'adjusted',
                'lock_version' => $lockedSale->lock_version + 1,
            ])->save();

            // 5. Registro de auditoria
            $this->events->record($actor, $context, 'sale.adjusted', $lockedSale, [
                'sale_id' => $lockedSale->getKey(),
                'from_status' => 'finalized',
                'to_status' => 'adjusted',
                'reason' => $reason,
                'lock_version' => $lockedSale->lock_version,
            ]);

            return $lockedSale;
        }, 5);
    }
}
