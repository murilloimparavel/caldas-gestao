<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\ClosingSession;
use App\Models\ClosingSessionPayment;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class FinanceDashboardController extends Controller
{
    /** @var array<string, string> */
    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Dinheiro',
        'pix' => 'PIX',
        'credit_card' => 'Cartão de crédito',
        'debit_card' => 'Cartão de débito',
        'bank_transfer' => 'Transferência',
        'permuta' => 'Permuta',
    ];

    public function __invoke(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', FinancialObligation::class);

        $filters = $this->filters($request);
        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();
        $timezone = $context->tenant->timezone ?: config('app.timezone');
        $nowInTenantTimezone = now($timezone);
        $start = Carbon::parse($filters['from'], $timezone)->startOfDay()->utc();
        $end = Carbon::parse($filters['to'], $timezone)->endOfDay()->utc();
        $today = now($timezone)->toDateString();
        $currentMonth = now($timezone)->month;
        $currentYear = now($timezone)->year;

        $currentCashBalanceCents = (int) CashShift::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'open')
            ->sum('expected_amount_cents');

        $baseObligationsQuery = FinancialObligation::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId);

        $basePaymentsQuery = ClosingSessionPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereHas('closingSession', fn (Builder $query) => $query
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'completed'));

        $monthStart = $nowInTenantTimezone->copy()->startOfMonth()->startOfDay()->utc();
        $monthEnd = $nowInTenantTimezone->copy()->endOfMonth()->endOfDay()->utc();

        $payableTodayCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')->where('status', 'pending')->whereDate('due_date', $today)->sum('amount_cents');
        $receivableTodayCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'receivable')->where('status', 'pending')->whereDate('due_date', $today)->sum('amount_cents');
        $payableMonthPendingCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')->where('status', 'pending')->whereMonth('due_date', $currentMonth)->whereYear('due_date', $currentYear)->sum('amount_cents');
        $receivableMonthPendingCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'receivable')->where('status', 'pending')->whereMonth('due_date', $currentMonth)->whereYear('due_date', $currentYear)->sum('amount_cents');
        $paidMonthCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')->where('status', 'paid')->whereMonth('paid_date', $currentMonth)->whereYear('paid_date', $currentYear)->sum('amount_cents');

        $receivedMonthObligations = (int) $this->withoutDuplicatedPackageReceivables((clone $baseObligationsQuery))
            ->where('type', 'receivable')->where('status', 'paid')->whereMonth('paid_date', $currentMonth)->whereYear('paid_date', $currentYear)->sum('amount_cents');
        $receivedMonthClosings = (int) (clone $basePaymentsQuery)
            ->whereBetween('recorded_at', [$monthStart, $monthEnd])
            ->selectRaw('COALESCE(SUM(CASE WHEN is_reversal THEN -amount_cents ELSE amount_cents END), 0) as total')
            ->value('total');
        $receivedMonthCents = $receivedMonthObligations + $receivedMonthClosings;

        $filteredObligations = $this->applyFilters(clone $baseObligationsQuery, $filters, $start, $end);
        $filteredPayments = $this->applyFilters(clone $basePaymentsQuery, $filters, $start, $end);
        $availablePaymentMethods = (clone $basePaymentsQuery)
            ->whereNotNull('payment_method')->distinct()->pluck('payment_method')
            ->merge((clone $baseObligationsQuery)->whereNotNull('payment_method')->distinct()->pluck('payment_method'))
            ->unique()->sort()->values()
            ->map(fn (string $method) => [
                'value' => $method,
                'label' => self::PAYMENT_METHOD_LABELS[$method] ?? strtoupper(str_replace('_', ' ', $method)),
            ]);
        $obligationSummary = (clone $filteredObligations)
            ->selectRaw('type, status, COALESCE(SUM(amount_cents), 0) as total')
            ->groupBy('type', 'status')->get()->keyBy(fn (FinancialObligation $row) => $row->type.':'.$row->status);

        $summaryPayments = clone $filteredPayments;
        if ($filters['source'] === 'obligations' || $filters['type'] === 'payable') {
            $summaryPayments->whereRaw('1 = 0');
        }

        $paymentSummary = $summaryPayments
            ->selectRaw('payment_method, COALESCE(SUM(CASE WHEN is_reversal THEN -amount_cents ELSE amount_cents END), 0) as total')
            ->groupBy('payment_method')->get()->keyBy(fn (ClosingSessionPayment $payment) => $payment->payment_method);

        $receivedObligationQuery = (clone $filteredObligations)
            ->where('type', 'receivable')->where('status', 'paid');
        if ($filters['source'] === 'all') {
            $receivedObligationQuery = $this->withoutDuplicatedPackageReceivables($receivedObligationQuery);
        }
        if ($filters['source'] === 'closings' || $filters['type'] === 'payable') {
            $receivedObligationQuery->whereRaw('1 = 0');
        }
        $receivedObligationCents = (int) $receivedObligationQuery->sum('amount_cents');
        $receivedClosingCents = (int) $paymentSummary->sum('total');
        $receivedCents = $receivedObligationCents + $receivedClosingCents;
        $paidCents = $filters['source'] === 'closings' || $filters['type'] === 'receivable'
            ? 0
            : (int) ($obligationSummary->get('payable:paid')->total ?? 0);
        $receivablePendingCents = $filters['source'] === 'closings' || $filters['type'] === 'payable'
            ? 0
            : (int) ($obligationSummary->get('receivable:pending')->total ?? 0);
        $payablePendingCents = $filters['source'] === 'closings' || $filters['type'] === 'receivable'
            ? 0
            : (int) ($obligationSummary->get('payable:pending')->total ?? 0);
        if ($filters['status'] === 'paid' || $filters['status'] === 'reversed') {
            $receivablePendingCents = 0;
            $payablePendingCents = 0;
        } elseif ($filters['status'] === 'pending') {
            $paidCents = 0;
            $receivedObligationCents = 0;
            $receivedClosingCents = 0;
        } elseif ($filters['status'] === 'cancelled') {
            $paidCents = 0;
            $receivablePendingCents = 0;
            $payablePendingCents = 0;
            $receivedObligationCents = 0;
            $receivedClosingCents = 0;
        }

        $movements = collect();

        if ($filters['source'] !== 'closings') {
            $obligationsQuery = clone $filteredObligations;
            if ($filters['source'] === 'all') {
                $obligationsQuery = $this->withoutDuplicatedPackageReceivables($obligationsQuery);
            }
            $obligations = $obligationsQuery
                ->with(['category:id,name', 'supplier:id,name', 'customer:id,name'])
                ->latest('updated_at')->take(100)->get();

            foreach ($obligations as $obligation) {
                $isPaid = $obligation->status === 'paid';
                $movements->push([
                    'id' => 'obligation:'.$obligation->getKey(),
                    'date' => ($isPaid ? $obligation->paid_date : $obligation->due_date)?->toDateString(),
                    'description' => $obligation->description,
                    'counterparty' => $obligation->customer->name ?? $obligation->supplier->name ?? $obligation->category->name ?? 'Geral',
                    'source' => 'obligation',
                    'source_label' => 'Lançamento financeiro',
                    'type' => $obligation->type,
                    'status' => $obligation->status,
                    'payment_method' => $obligation->payment_method,
                    'payment_method_label' => self::PAYMENT_METHOD_LABELS[$obligation->payment_method] ?? strtoupper((string) $obligation->payment_method),
                    'amount_cents' => $obligation->amount_cents,
                    'signed_amount_cents' => $obligation->status === 'cancelled'
                        ? 0
                        : ($obligation->type === 'payable' ? -$obligation->amount_cents : $obligation->amount_cents),
                    'reference_url' => route('financial_obligations.show', $obligation),
                ]);
            }
        }

        if ($filters['source'] !== 'obligations') {
            $payments = (clone $filteredPayments)
                ->with(['closingSession.sales.items', 'closingSession.sales.customer:id,name'])
                ->latest('recorded_at')->take(100)->get();

            foreach ($payments as $payment) {
                $session = $payment->closingSession;
                $sales = $session->sales;
                $hasPackage = $sales->flatMap->items->contains(fn ($item): bool => $item->item_type === 'package');
                $description = $hasPackage ? 'Venda de pacote' : 'Recebimento de comanda';
                $customer = $sales->first()?->customer?->name;

                $movements->push([
                    'id' => 'closing-payment:'.$payment->getKey(),
                    'date' => $payment->recorded_at->timezone($timezone)->toDateString(),
                    'description' => $description.($session->receipt_number ? ' #'.$session->receipt_number : ''),
                    'counterparty' => $customer ?? 'Cliente',
                    'source' => 'closing',
                    'source_label' => 'Comanda',
                    'type' => 'receivable',
                    'status' => $payment->is_reversal ? 'reversed' : 'paid',
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => self::PAYMENT_METHOD_LABELS[$payment->payment_method] ?? strtoupper((string) $payment->payment_method),
                    'amount_cents' => $payment->amount_cents,
                    'signed_amount_cents' => $payment->is_reversal ? -$payment->amount_cents : $payment->amount_cents,
                    'reference_url' => route('closing-sessions.show', $session),
                ]);
            }
        }

        $movements = $movements
            ->sortByDesc(fn (array $movement) => $movement['date'].'|'.$movement['id'])
            ->take(100)->values();

        $overdueQuery = (clone $baseObligationsQuery)->where('status', 'pending')->whereDate('due_date', '<', $today);

        return Inertia::render('finance/dashboard', [
            'metrics' => [
                'current_cash_balance_cents' => $currentCashBalanceCents,
                'projected_balance_cents' => $currentCashBalanceCents + $receivablePendingCents - $payablePendingCents,
                'payable_today_cents' => $payableTodayCents,
                'receivable_today_cents' => $receivableTodayCents,
                'payable_month_pending_cents' => $payableMonthPendingCents,
                'receivable_month_pending_cents' => $receivableMonthPendingCents,
                'paid_month_cents' => $paidMonthCents,
                'received_month_cents' => $receivedMonthCents,
                'overdue_count' => $overdueQuery->count(),
                'overdue_amount_cents' => (int) (clone $overdueQuery)->sum('amount_cents'),
                'filtered_received_cents' => $receivedCents,
                'filtered_received_closings_cents' => $receivedClosingCents,
                'filtered_received_obligations_cents' => $receivedObligationCents,
                'filtered_paid_cents' => $paidCents,
                'filtered_receivable_pending_cents' => $receivablePendingCents,
                'filtered_payable_pending_cents' => $payablePendingCents,
            ],
            'filters' => $filters,
            'datePresets' => [
                'today' => ['from' => $nowInTenantTimezone->toDateString(), 'to' => $nowInTenantTimezone->toDateString()],
                'month' => ['from' => $nowInTenantTimezone->copy()->startOfMonth()->toDateString(), 'to' => $nowInTenantTimezone->copy()->endOfMonth()->toDateString()],
                'previous' => ['from' => $nowInTenantTimezone->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => $nowInTenantTimezone->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            ],
            'filterOptions' => [
                'sources' => ['all' => 'Todas as origens', 'closings' => 'Comandas', 'obligations' => 'Lançamentos'],
                'types' => ['all' => 'Todas as naturezas', 'receivable' => 'Receitas', 'payable' => 'Despesas'],
                'statuses' => ['all' => 'Todas as situações', 'pending' => 'Pendente', 'paid' => 'Realizado', 'reversed' => 'Estornado', 'cancelled' => 'Cancelado'],
                'paymentMethods' => $availablePaymentMethods,
            ],
            'movements' => $movements,
            'upcomingObligations' => (clone $baseObligationsQuery)->with(['category:id,name', 'supplier:id,name', 'customer:id,name'])->where('status', 'pending')->whereDate('due_date', '>=', $today)->orderBy('due_date')->take(8)->get(),
        ]);
    }

    /** @return array{from: string, to: string, source: string, type: string, status: string, payment_method: string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'source' => ['nullable', 'in:all,closings,obligations'],
            'type' => ['nullable', 'in:all,receivable,payable'],
            'status' => ['nullable', 'in:all,pending,paid,reversed,cancelled'],
            'payment_method' => ['nullable', 'string', 'max:40'],
        ]);

        $timezone = $request->attributes->get(TenantContext::class)?->tenant?->timezone ?: config('app.timezone');
        $from = $validated['from'] ?? now($timezone)->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now($timezone)->endOfMonth()->toDateString();

        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366) {
            throw ValidationException::withMessages(['to' => 'O período máximo permitido é de 367 dias.']);
        }

        return [
            'from' => $from,
            'to' => $to,
            'source' => $validated['source'] ?? 'all',
            'type' => $validated['type'] ?? 'all',
            'status' => $validated['status'] ?? 'all',
            'payment_method' => $validated['payment_method'] ?? 'all',
        ];
    }

    /** @param Builder<FinancialObligation>|Builder<ClosingSessionPayment> $query
     * @param  array{from: string, to: string, source: string, type: string, status: string, payment_method: string}  $filters
     */
    /**
     * @template TModel of FinancialObligation|ClosingSessionPayment
     *
     * @param  Builder<TModel>  $query
     * @param  array{from: string, to: string, source: string, type: string, status: string, payment_method: string}  $filters
     * @return Builder<TModel>
     */
    private function applyFilters(Builder $query, array $filters, Carbon $start, Carbon $end): Builder
    {
        if ($query->getModel() instanceof FinancialObligation) {
            $query->where(function (Builder $inner) use ($filters): void {
                $inner->where(function (Builder $pending) use ($filters): void {
                    $pending->where('status', 'pending')->whereDate('due_date', '>=', $filters['from'])->whereDate('due_date', '<=', $filters['to']);
                })->orWhere(function (Builder $settled) use ($filters): void {
                    $settled->where(function (Builder $dateQuery) use ($filters): void {
                        $dateQuery->where('status', 'paid')->whereDate('paid_date', '>=', $filters['from'])->whereDate('paid_date', '<=', $filters['to'])
                            ->orWhere('status', 'cancelled')->whereDate('due_date', '>=', $filters['from'])->whereDate('due_date', '<=', $filters['to']);
                    });
                });
            });

            if ($filters['type'] !== 'all') {
                $query->where('type', $filters['type']);
            }
            if ($filters['status'] !== 'all' && $filters['status'] !== 'reversed') {
                $query->where('status', $filters['status']);
            } elseif ($filters['status'] === 'reversed') {
                $query->whereRaw('1 = 0');
            }
            if ($filters['payment_method'] !== 'all') {
                $query->where('payment_method', $filters['payment_method']);
            }

            return $query;
        }

        $query->whereBetween('recorded_at', [$start, $end]);
        if ($filters['type'] === 'payable') {
            $query->whereRaw('1 = 0');
        }
        if ($filters['status'] === 'pending' || $filters['status'] === 'cancelled') {
            $query->whereRaw('1 = 0');
        } elseif ($filters['status'] === 'paid') {
            $query->where('is_reversal', false);
            $closingPaymentsTable = $query->getModel()->getTable();
            $query->whereNotExists(static function ($reversal) use ($closingPaymentsTable): void {
                $reversal->selectRaw('1')
                    ->from($closingPaymentsTable.' as payment_reversals')
                    ->whereColumn('payment_reversals.reversal_of_id', $closingPaymentsTable.'.id')
                    ->whereColumn('payment_reversals.tenant_id', $closingPaymentsTable.'.tenant_id')
                    ->whereColumn('payment_reversals.unit_id', $closingPaymentsTable.'.unit_id')
                    ->where('payment_reversals.is_reversal', true);
            });
        } elseif ($filters['status'] === 'reversed') {
            $query->where('is_reversal', true);
        }
        if ($filters['payment_method'] !== 'all') {
            $query->where('payment_method', $filters['payment_method']);
        }

        return $query;
    }

    /**
     * Remove a paid package receivable when its package was sold through a
     * completed closing session. The closing payment is the source of truth
     * for that sale and the obligation is a secondary representation.
     *
     * @param  Builder<FinancialObligation>  $query
     * @return Builder<FinancialObligation>
     */
    private function withoutDuplicatedPackageReceivables(Builder $query): Builder
    {
        $saleItemsTable = (new SaleItem)->getTable();
        $salesTable = (new Sale)->getTable();
        $customerPackagesTable = (new CustomerPackage)->getTable();
        $closingSessionsTable = (new ClosingSession)->getTable();
        $closingPaymentsTable = (new ClosingSessionPayment)->getTable();

        return $query->where(function (Builder $outer) use ($saleItemsTable, $salesTable, $customerPackagesTable, $closingSessionsTable, $closingPaymentsTable): void {
            $outer->where('status', '!=', 'paid')
                ->orWhere('type', '!=', 'receivable')
                ->orWhereNotExists(static function ($subquery) use ($saleItemsTable, $salesTable, $customerPackagesTable, $closingSessionsTable, $closingPaymentsTable): void {
                    $subquery->selectRaw('1')
                        ->from($saleItemsTable.' as package_items')
                        ->join($salesTable.' as package_sales', 'package_sales.id', '=', 'package_items.sale_id')
                        ->join('closing_session_sales as linked_sales', 'linked_sales.sale_id', '=', 'package_sales.id')
                        ->join($closingSessionsTable.' as linked_sessions', 'linked_sessions.id', '=', 'linked_sales.closing_session_id')
                        ->join($closingPaymentsTable.' as linked_payments', 'linked_payments.closing_session_id', '=', 'linked_sessions.id')
                        ->join($customerPackagesTable.' as linked_packages', 'linked_packages.id', '=', 'package_items.customer_package_id')
                        ->whereColumn('package_items.customer_package_id', 'financial_obligations.customer_package_id')
                        ->whereColumn('package_items.tenant_id', 'financial_obligations.tenant_id')
                        ->whereColumn('package_items.unit_id', 'financial_obligations.unit_id')
                        ->whereColumn('linked_packages.tenant_id', 'financial_obligations.tenant_id')
                        ->whereColumn('linked_packages.unit_id', 'financial_obligations.unit_id')
                        ->whereColumn('financial_obligations.amount_cents', 'linked_packages.price_cents_snapshot')
                        ->whereNull('linked_packages.deleted_at')
                        ->where('package_items.item_type', 'package')
                        ->whereNull('package_items.deleted_at')
                        ->where('package_sales.status', 'finalized')
                        ->whereNull('package_sales.deleted_at')
                        ->where('linked_sessions.status', 'completed')
                        ->where('linked_payments.is_reversal', false)
                        ->whereNotExists(static function ($reversal) use ($closingPaymentsTable): void {
                            $reversal->selectRaw('1')
                                ->from($closingPaymentsTable.' as payment_reversals')
                                ->whereColumn('payment_reversals.reversal_of_id', 'linked_payments.id')
                                ->whereColumn('payment_reversals.tenant_id', 'linked_payments.tenant_id')
                                ->whereColumn('payment_reversals.unit_id', 'linked_payments.unit_id')
                                ->where('payment_reversals.is_reversal', true);
                        });
                });
        });
    }
}
