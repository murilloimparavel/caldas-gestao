<?php

namespace App\Actions\Closing;

use App\Actions\Finance\Commissions\AccrueCommissionsForSale;
use App\Actions\Marketing\Retention\RecordCustomerActivity;
use App\Actions\Operational\OperationalAction;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageService;
use App\Models\InventoryMovement;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleStatusHistory;
use App\Models\User;
use App\Support\AuditEventWriter;
use App\Support\AuthorizationService;
use App\Support\IdentityEventRecorder;
use App\Support\LegacyPackageReconciliation;
use App\Support\OutboxEventStore;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        private readonly LegacyPackageReconciliation $packageEvidence = new LegacyPackageReconciliation,
    ) {
        parent::__construct($authorization, $events);
    }

    /**
     * @param  array{payment_allocations?: list<array{method: string, amount_cents: int, tendered_cents?: int|null}>, payment_method?: string|null, cash_received_cents?: int|null}  $data
     * @return list<array{method: string, amount_cents: int, tendered_cents?: int|null}>
     */
    private function paymentAllocations(array $data, int $totalCents): array
    {
        if ($totalCents === 0) {
            return [];
        }

        if (isset($data['payment_allocations'])) {
            return $data['payment_allocations'];
        }

        return [[
            'method' => $data['payment_method'] ?? '',
            'amount_cents' => $totalCents,
            'tendered_cents' => ($data['payment_method'] ?? null) === 'cash'
                ? (int) ($data['cash_received_cents'] ?? 0)
                : null,
        ]];
    }

    /**
     * @param  array{
     *     sale_ids: list<string>,
     *     expected_total_cents?: int|null,
     *     payment_method?: string|null,
     *     cash_received_cents?: int|null,
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
                ->with(['customer', 'category', 'items.service', 'items.product', 'items.professional', 'items.customerPackage', 'appointmentLink.appointment'])
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

            $saleItemIds = $sales->flatMap(fn (Sale $sale) => $sale->items->pluck('id'))->all();
            $reservedUsages = PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereIn('sale_item_id', $saleItemIds)
                ->where('status', 'reserved')
                ->orderBy('customer_package_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservedUsages as $usage) {
                $item = $sales->flatMap(fn (Sale $sale) => $sale->items)->firstWhere('id', $usage->sale_item_id);
                if ($item === null || $item->covered_quantity !== $usage->sessions_reserved || $item->service_id !== $usage->service_id) {
                    throw ValidationException::withMessages([
                        'sale_ids' => 'A reserva do pacote não corresponde ao serviço da comanda.',
                    ]);
                }
            }

            $reservedItemIds = $reservedUsages->pluck('sale_item_id')->all();
            foreach ($sales->flatMap(fn (Sale $sale) => $sale->items) as $item) {
                if ($item->covered_quantity > 0 && ! in_array($item->getKey(), $reservedItemIds, true)) {
                    throw ValidationException::withMessages([
                        'sale_ids' => 'Um serviço marcado como coberto não possui reserva de saldo válida.',
                    ]);
                }
            }

            foreach ($reservedUsages->groupBy('customer_package_id') as $customerPackageId => $usages) {
                /** @var CustomerPackage $package */
                $package = CustomerPackage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->whereKey($customerPackageId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($package->status !== 'active' || ($package->expires_at !== null && $package->expires_at->endOfDay()->isPast())) {
                    throw ValidationException::withMessages([
                        'sale_ids' => "O pacote {$package->name_snapshot} não está ativo ou venceu; atualize a comanda antes de fechar.",
                    ]);
                }

                $packageSaleItems = SaleItem::query()
                    ->where('customer_package_id', $package->getKey())
                    ->with('sale.closingSessions.payments')
                    ->get();
                if (! $this->packageEvidence->hasStrictPaidSaleEvidence(
                    $package,
                    $packageSaleItems,
                    Schema::hasColumn('financial_obligations', 'customer_package_id'),
                )) {
                    throw ValidationException::withMessages([
                        'sale_ids' => "O pacote {$package->name_snapshot} não tem comprovação válida de pagamento; solicite revisão antes de fechar.",
                    ]);
                }

                $sessionsToConsume = (int) $usages->sum('sessions_reserved');
                if ($package->remaining_sessions < $sessionsToConsume) {
                    throw ValidationException::withMessages([
                        'sale_ids' => "O saldo do pacote {$package->name_snapshot} mudou e não cobre mais os serviços reservados.",
                    ]);
                }

                foreach ($usages->groupBy('service_id') as $serviceId => $serviceUsages) {
                    $serviceBalance = CustomerPackageService::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->where('customer_package_id', $package->getKey())
                        ->where('service_id', $serviceId)
                        ->lockForUpdate()
                        ->first();
                    $serviceQuantity = (int) $serviceUsages->sum('sessions_reserved');

                    if ($serviceBalance === null || $serviceBalance->remaining_quantity < $serviceQuantity) {
                        throw ValidationException::withMessages([
                            'sale_ids' => "O saldo do serviço no pacote {$package->name_snapshot} mudou e não cobre mais os serviços reservados.",
                        ]);
                    }

                    $serviceBalance->forceFill([
                        'remaining_quantity' => $serviceBalance->remaining_quantity - $serviceQuantity,
                    ])->save();
                }

                $newRemaining = $package->remaining_sessions - $sessionsToConsume;
                $packageStatus = $newRemaining === 0 ? 'completed' : $package->status;
                $package->forceFill([
                    'remaining_sessions' => $newRemaining,
                    'status' => $packageStatus,
                    'lock_version' => $package->lock_version + 1,
                ])->save();

                foreach ($usages as $reservation) {
                    /** @var PackageUsage $consumption */
                    $consumption = PackageUsage::query()->create([
                        'id' => (string) Str::uuid7(),
                        'tenant_id' => $tenantId,
                        'unit_id' => $unitId,
                        'customer_package_id' => $package->getKey(),
                        'service_id' => $reservation->service_id,
                        'sale_id' => $reservation->sale_id,
                        'sale_item_id' => $reservation->sale_item_id,
                        'sessions_consumed' => $reservation->sessions_reserved,
                        'user_id' => $actor->getKey(),
                    ]);

                    $reservation->forceFill([
                        'status' => 'consumed',
                        'package_usage_id' => $consumption->getKey(),
                    ])->save();

                    $this->events->record($actor, $context, 'package_usage.consumed', $consumption, [
                        'customer_package_id' => $package->getKey(),
                        'package_usage_id' => $consumption->getKey(),
                        'sessions_consumed' => $consumption->sessions_consumed,
                    ]);
                }

                $this->events->record($actor, $context, 'customer_package.consumed', $package, [
                    'customer_package_id' => $package->getKey(),
                    'sessions_consumed' => $sessionsToConsume,
                    'remaining_sessions' => $newRemaining,
                    'status' => $packageStatus,
                ]);
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

            $allocations = $this->paymentAllocations($data, $calculatedFinalTotalCents);
            $allocationTotal = array_sum(array_map(static fn (array $allocation): int => $allocation['amount_cents'], $allocations));
            if ($allocationTotal !== $calculatedFinalTotalCents) {
                throw ValidationException::withMessages(['payment_allocations' => 'A soma das parcelas deve corresponder exatamente ao total da comanda.']);
            }

            $packageItems = $sales->flatMap(fn (Sale $sale) => $sale->items)
                ->filter(static fn (SaleItem $item): bool => $item->item_type === 'package');

            foreach ($packageItems as $packageItem) {
                $packageSale = $sales->firstWhere('id', $packageItem->sale_id);

                if ($packageItem->total_cents <= 0) {
                    throw ValidationException::withMessages([
                        'sale_ids' => 'A linha do pacote precisa ter um valor efetivo positivo para ser ativada.',
                    ]);
                }

                if ($packageSale === null || $packageSale->final_amount_cents <= 0 || $allocationTotal <= 0) {
                    throw ValidationException::withMessages([
                        'sale_ids' => 'O pacote não pode ser ativado sem valor líquido positivo recebido na comanda.',
                    ]);
                }
            }

            $hasCash = collect($allocations)->contains(fn (array $allocation): bool => $allocation['method'] === 'cash');
            $cashShift = CashShift::query()
                ->where('tenant_id', $tenantId)->where('unit_id', $unitId)
                ->where('opened_by_user_id', $actor->getKey())->where('status', 'open')
                ->lockForUpdate()->first();
            if ($hasCash && $cashShift === null) {
                throw ValidationException::withMessages(['payment_allocations' => 'Abra um novo turno de caixa para receber parcelas em dinheiro.']);
            }
            if ($calculatedFinalTotalCents === 0 && $allocations !== []) {
                throw ValidationException::withMessages(['payment_allocations' => 'Uma comanda sem valor a receber não pode registrar pagamento.']);
            }
            foreach ($allocations as $index => $allocation) {
                $method = $allocation['method'];
                $amount = $allocation['amount_cents'];
                $tendered = $allocation['tendered_cents'] ?? null;
                if ($amount <= 0 || ! in_array($method, ['pix', 'debit_card', 'credit_card', 'cash', 'permuta'], true)) {
                    throw ValidationException::withMessages(["payment_allocations.{$index}" => 'A parcela de pagamento é inválida.']);
                }
                if ($method === 'cash' && (! is_numeric($tendered) || (int) $tendered < $amount)) {
                    $errorKey = array_key_exists('payment_allocations', $data)
                        ? "payment_allocations.{$index}.tendered_cents"
                        : 'cash_received_cents';
                    throw ValidationException::withMessages([$errorKey => 'O valor entregue deve ser igual ou superior ao valor aplicado em dinheiro.']);
                }
            }
            $legacyPaymentMethod = count($allocations) === 1 ? $allocations[0]['method'] : null;

            if (isset($data['expected_total_cents']) && (int) $data['expected_total_cents'] !== $calculatedFinalTotalCents) {
                throw ValidationException::withMessages([
                    'expected_total_cents' => 'O total esperado difere do valor calculado das comandas selecionadas.',
                ]);
            }

            $cashAllocations = collect($allocations)->filter(
                static fn (array $allocation): bool => $allocation['method'] === 'cash',
            );
            $cashReceivedCents = $cashAllocations->isNotEmpty()
                ? (int) $cashAllocations->sum(static fn (array $allocation): int => (int) ($allocation['tendered_cents'] ?? 0))
                : null;
            $cashAppliedCents = (int) $cashAllocations->sum(static fn (array $allocation): int => $allocation['amount_cents']);
            $cashChangeCents = $cashReceivedCents !== null
                ? $cashReceivedCents - $cashAppliedCents
                : null;

            if ($cashReceivedCents !== null && $cashChangeCents < 0) {
                throw ValidationException::withMessages([
                    'cash_received_cents' => 'O valor recebido em dinheiro não pode ser menor que o total da comanda.',
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
                'cash_received_cents' => $cashReceivedCents,
                'cash_change_cents' => $cashChangeCents,
                'payment_method' => $legacyPaymentMethod,
                'payment_allocations' => collect($allocations)->map(function (array $allocation): array {
                    $tendered = $allocation['tendered_cents'] ?? null;

                    return [
                        'method' => $allocation['method'],
                        'amount_cents' => $allocation['amount_cents'],
                        'tendered_cents' => $allocation['method'] === 'cash' ? $tendered : null,
                        'change_cents' => $allocation['method'] === 'cash' ? (int) $tendered - $allocation['amount_cents'] : 0,
                    ];
                })->values()->all(),
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
                        'covered_quantity' => $item->covered_quantity,
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
                'cash_received_cents' => $cashReceivedCents,
                'cash_change_cents' => $cashChangeCents,
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
                $tendered = $method === 'cash' ? ($allocation['tendered_cents'] ?? null) : null;
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

            // Keep the invariant explicit: a package is usable only after its sale
            // is finalized in the same completed closing transaction.
            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    if ($item->item_type !== 'package' || $item->customer_package_id === null) {
                        continue;
                    }

                    /** @var CustomerPackage $package */
                    $package = CustomerPackage::query()
                        ->where('tenant_id', $tenantId)
                        ->where('unit_id', $unitId)
                        ->whereKey($item->customer_package_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($sale->status !== 'finalized'
                        || $package->status !== 'pending'
                        || $package->sale_id !== $sale->getKey()
                        || $package->customer_id !== $sale->customer_id
                        || $package->package_template_id !== $item->package_template_id) {
                        throw ValidationException::withMessages([
                            'sale_ids' => 'A instância do pacote exige uma comanda finalizada do mesmo cliente e modelo.',
                        ]);
                    }

                    $activatedAt = now();
                    $expiresAt = $package->validity_days_snapshot !== null && $package->validity_days_snapshot > 0
                        ? $activatedAt->copy()->addDays($package->validity_days_snapshot)->toDateString()
                        : null;

                    $package->forceFill([
                        'status' => $package->remaining_sessions === 0 ? 'completed' : 'active',
                        'activated_at' => $activatedAt,
                        'expires_at' => $expiresAt,
                        'sale_id' => $sale->getKey(),
                        'lock_version' => $package->lock_version + 1,
                    ])->save();

                    $this->events->record($actor, $context, 'customer_package.activated', $package, [
                        'customer_package_id' => $package->getKey(),
                        'sale_id' => $sale->getKey(),
                        'sale_item_id' => $item->getKey(),
                        'activated_at' => $activatedAt->toISOString(),
                        'expires_at' => $expiresAt,
                        'status' => $package->status,
                    ]);
                }
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
