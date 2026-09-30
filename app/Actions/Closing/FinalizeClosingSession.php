<?php

namespace App\Actions\Closing;

use App\Actions\Finance\Commissions\AccrueCommissionsForSale;
use App\Actions\Marketing\Retention\RecordCustomerActivity;
use App\Actions\Operational\OperationalAction;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
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

final class FinalizeClosingSession extends OperationalAction
{
    public function __construct(
        AuthorizationService $authorization = new AuthorizationService,
        IdentityEventRecorder $events = new IdentityEventRecorder(new AuditEventWriter, new OutboxEventStore),
        private readonly AccrueCommissionsForSale $accrueCommissions = new AccrueCommissionsForSale,
        private readonly RecordCustomerActivity $recordCustomerActivity = new RecordCustomerActivity,
    ) {
        parent::__construct($authorization, $events);
    }

    /**
     * @param  array{
     *     sale_ids: list<string>,
     *     expected_total_cents?: int|null,
     *     payment_method?: string|null,
     *     payment_allocations?: list<array{method: string, amount_cents: int, tendered_cents?: int|null}>,
     *     notes?: string|null,
     *     lock_versions?: array<string, int>|null
     * }  $data
     */
    public function handle(User $actor, TenantContext $context, array $data): ClosingSession
    {
        $unit = $context->unit;

        if ($unit === null || ! $context->user->is($actor) || (! $this->authorization->can($actor, $context, 'sale.close', $unit) && ! $this->authorization->can($actor, $context, 'sale.manage', $unit))) {
            throw new AuthorizationException('O operador não possui permissão para realizar fechamento de comandas.');
        }

        $tenantId = $context->tenant->getKey();
        $unitId = $unit->getKey();

        return DB::transaction(function () use ($actor, $context, $unit, $data, $tenantId, $unitId): ClosingSession {
            $saleIds = array_values(array_unique((array) $data['sale_ids']));

            if (empty($saleIds)) {
                throw ValidationException::withMessages([
                    'sale_ids' => 'Nenhuma comanda informada para o fechamento.',
                ]);
            }

            /** @var Collection<int, Sale> $sales */
            $sales = Sale::query()
                ->with(['customer', 'category', 'items.service', 'items.product', 'items.professional', 'appointmentLink.appointment'])
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereIn('id', $saleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sales->count() !== count($saleIds)) {
                throw ValidationException::withMessages([
                    'sale_ids' => 'Uma ou mais comandas selecionadas não foram encontradas nesta unidade.',
                ]);
            }

            // Concurrency lock_version validation
            if (isset($data['lock_versions'])) {
                foreach ($sales as $sale) {
                    if (isset($data['lock_versions'][$sale->getKey()])) {
                        $expected = (int) $data['lock_versions'][$sale->getKey()];
                        if ($sale->lock_version !== $expected) {
                            throw new ConflictHttpException("A comanda #{$sale->getKey()} foi modificada concorrentemente.");
                        }
                    }
                }
            }

            // Status check: only draft, open, ready_to_bill
            foreach ($sales as $sale) {
                if (! in_array($sale->status, ['draft', 'open', 'ready_to_bill'], true)) {
                    throw ValidationException::withMessages([
                        'sale_ids' => "A comanda #{$sale->getKey()} possui status '{$sale->status}' e não pode ser finalizada.",
                    ]);
                }
            }

            // Validate uniform closing_subject (ADR-003 & PRD)
            $firstSale = $sales->first();
            $firstCustomerId = $firstSale->customer_id;
            $firstReference = trim((string) $firstSale->reference_label);

            if ($firstCustomerId !== null) {
                foreach ($sales as $s) {
                    if ($s->customer_id !== $firstCustomerId) {
                        throw ValidationException::withMessages([
                            'closing_subject' => 'Todas as comandas selecionadas devem pertencer ao mesmo cliente ou à mesma referência.',
                        ]);
                    }
                }
                $closingSubject = "customer:{$firstCustomerId}";
            } elseif ($firstReference !== '') {
                foreach ($sales as $s) {
                    if (trim((string) $s->reference_label) !== $firstReference || $s->customer_id !== null) {
                        throw ValidationException::withMessages([
                            'closing_subject' => 'Todas as comandas selecionadas devem pertencer ao mesmo cliente ou à mesma referência.',
                        ]);
                    }
                }
                $closingSubject = 'reference:'.Str::slug($firstReference);
            } else {
                if ($sales->count() > 1) {
                    throw ValidationException::withMessages([
                        'closing_subject' => 'Comandas avulsas sem cliente ou referência identificada não podem ser consolidadas juntas.',
                    ]);
                }
                $closingSubject = "sale:{$firstSale->getKey()}";
            }

            $totalGrossCents = (int) $sales->sum('total_amount_cents');
            $totalDiscountCents = (int) $sales->sum('discount_amount_cents');
            $calculatedFinalTotalCents = (int) $sales->sum('final_amount_cents');

            $allocations = $data['payment_allocations'] ?? [[
                'method' => $data['payment_method'] ?? '',
                'amount_cents' => $calculatedFinalTotalCents,
            ]];
            $allocationTotal = array_sum(array_map(static fn (array $allocation): int => (int) ($allocation['amount_cents'] ?? 0), $allocations));
            if ($allocationTotal !== $calculatedFinalTotalCents) {
                throw ValidationException::withMessages(['payment_allocations' => 'A soma das parcelas deve corresponder exatamente ao total da comanda.']);
            }
            $hasCash = collect($allocations)->contains(fn (array $allocation): bool => ($allocation['method'] ?? null) === 'cash');
            $cashShift = CashShift::query()
                ->where('tenant_id', $tenantId)->where('unit_id', $unitId)
                ->where('opened_by_user_id', $actor->getKey())->where('status', 'open')
                ->lockForUpdate()->first();
            if ($hasCash && $cashShift === null) {
                throw ValidationException::withMessages(['payment_allocations' => 'Abra um novo turno de caixa para receber parcelas em dinheiro.']);
            }
            foreach ($allocations as $index => $allocation) {
                $method = (string) ($allocation['method'] ?? '');
                $amount = (int) ($allocation['amount_cents'] ?? 0);
                $tendered = $allocation['tendered_cents'] ?? null;
                if ($amount <= 0 || ! in_array($method, ['pix', 'debit_card', 'credit_card', 'cash', 'permuta'], true)) {
                    throw ValidationException::withMessages(["payment_allocations.{$index}" => 'A parcela de pagamento é inválida.']);
                }
                if ($method === 'cash' && (! is_numeric($tendered) || (int) $tendered < $amount)) {
                    throw ValidationException::withMessages(["payment_allocations.{$index}.tendered_cents" => 'O valor entregue deve ser igual ou superior ao valor aplicado em dinheiro.']);
                }
            }
            $legacyPaymentMethod = count($allocations) === 1 ? $allocations[0]['method'] : null;

            if (isset($data['expected_total_cents']) && (int) $data['expected_total_cents'] !== $calculatedFinalTotalCents) {
                throw ValidationException::withMessages([
                    'expected_total_cents' => 'O total esperado difere do valor calculado das comandas selecionadas.',
                ]);
            }

            $datePrefix = now()->format('Ymd');
            $receiptNumber = 'REC-'.$datePrefix.'-'.strtoupper(Str::random(6));

            $receiptPayload = [
                'receipt_number' => $receiptNumber,
                'issued_at' => now()->toISOString(),
                'tenant' => [
                    'id' => $context->tenant->getKey(),
                    'name' => $context->tenant->name,
                    'slug' => $context->tenant->slug,
                ],
                'unit' => [
                    'id' => $unit->getKey(),
                    'name' => $unit->name,
                    'timezone' => $unit->timezone,
                ],
                'closed_by' => [
                    'id' => $actor->getKey(),
                    'name' => $actor->name,
                    'email' => $actor->email,
                ],
                'closing_subject' => $closingSubject,
                'customer' => $firstSale->customer ? [
                    'id' => $firstSale->customer->getKey(),
                    'name' => $firstSale->customer->name,
                    'phone' => $firstSale->customer->phone,
                    'email' => $firstSale->customer->email,
                ] : null,
                'currency' => 'BRL',
                'payment_method' => $legacyPaymentMethod,
                'payment_allocations' => collect($allocations)->map(fn (array $allocation): array => [
                    'method' => $allocation['method'],
                    'amount_cents' => (int) $allocation['amount_cents'],
                    'tendered_cents' => $allocation['method'] === 'cash' ? (int) $allocation['tendered_cents'] : null,
                    'change_cents' => $allocation['method'] === 'cash' ? (int) $allocation['tendered_cents'] - (int) $allocation['amount_cents'] : 0,
                ])->values()->all(),
                'totals' => [
                    'total_gross_cents' => $totalGrossCents,
                    'total_discount_cents' => $totalDiscountCents,
                    'final_total_cents' => $calculatedFinalTotalCents,
                    'sales_count' => $sales->count(),
                ],
                'sales' => $sales->map(fn (Sale $s): array => [
                    'id' => $s->getKey(),
                    'category_name' => $s->category_name_snapshot ?? ($s->category ? $s->category->name : 'Geral'),
                    'reference_label' => $s->reference_label,
                    'total_amount_cents' => $s->total_amount_cents,
                    'discount_amount_cents' => $s->discount_amount_cents,
                    'final_amount_cents' => $s->final_amount_cents,
                    'items' => $s->items->map(fn (SaleItem $item): array => [
                        'id' => $item->getKey(),
                        'item_type' => $item->item_type,
                        'name' => $item->name_snapshot,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                        'discount_cents' => $item->discount_cents,
                        'total_cents' => $item->total_cents,
                        'professional_name' => $item->professional?->name,
                    ])->values()->all(),
                ])->values()->all(),
                'notes' => $data['notes'] ?? null,
            ];

            /** @var ClosingSession $session */
            $session = ClosingSession::query()->create([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'closing_subject' => $closingSubject,
                'currency' => 'BRL',
                'payment_method' => $legacyPaymentMethod,
                'expected_total_cents' => $calculatedFinalTotalCents,
                'final_total_cents' => $calculatedFinalTotalCents,
                'status' => 'completed',
                'receipt_number' => $receiptNumber,
                'receipt_payload' => $receiptPayload,
                'closed_by_user_id' => $actor->getKey(),
                'lock_version' => 1,
            ]);

            $session->sales()->attach($sales->pluck('id')->all());

            foreach ($allocations as $allocation) {
                $amount = (int) $allocation['amount_cents'];
                $method = $allocation['method'];
                $tendered = $method === 'cash' ? (int) $allocation['tendered_cents'] : null;
                $change = $tendered === null ? 0 : $tendered - $amount;
                ClosingSessionPayment::query()->create([
                    'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'unit_id' => $unitId,
                    'closing_session_id' => $session->getKey(), 'cash_shift_id' => $method === 'cash' ? $cashShift?->getKey() : null,
                    'payment_method' => $method, 'amount_cents' => $amount, 'tendered_cents' => $tendered,
                    'change_cents' => $change, 'recorded_by_user_id' => $actor->getKey(), 'recorded_at' => now(),
                ]);
                if ($method === 'cash' && $cashShift !== null) {
                    CashMovement::query()->create([
                        'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'unit_id' => $unitId,
                        'cash_shift_id' => $cashShift->getKey(), 'type' => 'sale_inflow', 'amount_cents' => $amount,
                        'reason' => 'Recebimento da comanda #'.$session->getKey(), 'reference_type' => 'closing_session',
                        'reference_id' => $session->getKey(), 'user_id' => $actor->getKey(),
                    ]);
                    $cashShift->forceFill([
                        'expected_amount_cents' => $cashShift->expected_amount_cents + $amount,
                        'lock_version' => $cashShift->lock_version + 1,
                    ])->save();
                }
            }

            foreach ($sales as $lockedSale) {
                $fromStatus = $lockedSale->status;

                SaleStatusHistory::query()->create([
                    'id' => (string) Str::uuid7(),
                    'sale_id' => $lockedSale->getKey(),
                    'from_status' => $fromStatus,
                    'to_status' => 'finalized',
                    'user_id' => $actor->getKey(),
                    'reason' => 'Fechamento consolidado #'.$session->getKey(),
                ]);

                $lockedSale->forceFill([
                    'status' => 'finalized',
                    'lock_version' => $lockedSale->lock_version + 1,
                ])->save();

                $this->events->record($actor, $context, 'sale.finalized', $lockedSale, [
                    'from_status' => $fromStatus,
                    'to_status' => 'finalized',
                    'closing_session_id' => $session->getKey(),
                    'receipt_number' => $receiptNumber,
                    'lock_version' => $lockedSale->lock_version,
                ]);

                $this->recordCustomerActivity->handle($actor, $context, $lockedSale->customer_id, 'sale.finalized');

                $this->accrueCommissions->handle($actor, $context, $lockedSale);
            }

            $productQuantities = [];
            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    if ($item->item_type === 'product' && $item->product_id !== null) {
                        $productQuantities[$item->product_id] = ($productQuantities[$item->product_id] ?? 0) + $item->quantity;
                    }
                }
            }

            if (! empty($productQuantities)) {
                $productIds = array_keys($productQuantities);
                sort($productIds);

                $products = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($productQuantities as $productId => $qty) {
                    /** @var Product|null $product */
                    $product = $products->get($productId);
                    if ($product !== null) {
                        $previousStock = $product->current_stock;
                        $resultingStock = $previousStock - $qty;

                        $movement = InventoryMovement::query()->create([
                            'id' => (string) Str::uuid7(),
                            'tenant_id' => $tenantId,
                            'unit_id' => $unitId,
                            'product_id' => $product->getKey(),
                            'type' => 'sale_outflow',
                            'quantity' => $qty,
                            'unit_cost_cents' => $product->cost_price_cents,
                            'previous_stock' => $previousStock,
                            'resulting_stock' => $resultingStock,
                            'reason' => 'Baixa automática por venda no fechamento #'.$receiptNumber,
                            'reference_type' => 'closing_session',
                            'reference_id' => $session->getKey(),
                            'user_id' => $actor->getKey(),
                        ]);

                        $product->forceFill([
                            'current_stock' => $resultingStock,
                            'lock_version' => $product->lock_version + 1,
                        ])->save();

                        $this->events->record($actor, $context, 'inventory.moved', $product, [
                            'product_id' => $product->getKey(),
                            'inventory_movement_id' => $movement->getKey(),
                            'type' => 'sale_outflow',
                            'quantity' => $qty,
                            'previous_stock' => $previousStock,
                            'resulting_stock' => $resultingStock,
                            'lock_version' => $product->lock_version,
                        ]);
                    }
                }
            }

            $this->events->record($actor, $context, 'closing_session.completed', $session, [
                'closing_session_id' => $session->getKey(),
                'receipt_number' => $receiptNumber,
                'closing_subject' => $closingSubject,
                'final_total_cents' => $calculatedFinalTotalCents,
                'sale_ids' => $sales->pluck('id')->all(),
            ]);

            return $session;
        }, 5);
    }
}
