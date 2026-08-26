<?php

namespace App\Http\Controllers;

use App\Actions\Finance\Transactions\CancelFinancialObligation;
use App\Actions\Finance\Transactions\CreateFinancialObligation;
use App\Actions\Finance\Transactions\SettleFinancialObligation;
use App\Actions\Finance\Transactions\UpdateFinancialObligation;
use App\Http\Requests\FinancialObligationRequest;
use App\Http\Requests\SettleObligationRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\FinancialObligation;
use App\Models\Supplier;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FinancialObligationController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', FinancialObligation::class);

        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        $search = trim((string) $request->string('search'));
        $type = trim((string) $request->string('type'));
        $status = trim((string) $request->string('status'));
        $categoryId = trim((string) $request->string('category_id'));
        $supplierId = trim((string) $request->string('supplier_id'));
        $customerId = trim((string) $request->string('customer_id'));
        $dateStart = trim((string) $request->string('date_start'));
        $dateEnd = trim((string) $request->string('date_end'));

        $query = FinancialObligation::query()
            ->with(['category:id,name', 'supplier:id,name', 'customer:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($type, ['payable', 'receivable'], true)) {
            $query->where('type', $type);
        }

        if ($status === 'overdue') {
            $query->where('status', 'pending')->whereDate('due_date', '<', now()->toDateString());
        } elseif (in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($supplierId !== '') {
            $query->where('supplier_id', $supplierId);
        }

        if ($customerId !== '') {
            $query->where('customer_id', $customerId);
        }

        if ($dateStart !== '') {
            $query->whereDate('due_date', '>=', $dateStart);
        }

        if ($dateEnd !== '') {
            $query->whereDate('due_date', '<=', $dateEnd);
        }

        $obligations = $query->orderByDesc('due_date')->orderByDesc('created_at')->paginate(20)->withQueryString();

        $baseStatsQuery = FinancialObligation::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId);

        $today = now()->toDateString();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $metrics = [
            'total_payable_pending_cents' => (int) (clone $baseStatsQuery)->where('type', 'payable')->where('status', 'pending')->sum('amount_cents'),
            'total_receivable_pending_cents' => (int) (clone $baseStatsQuery)->where('type', 'receivable')->where('status', 'pending')->sum('amount_cents'),
            'total_overdue_cents' => (int) (clone $baseStatsQuery)->where('status', 'pending')->whereDate('due_date', '<', $today)->sum('amount_cents'),
            'total_paid_month_cents' => (int) (clone $baseStatsQuery)->where('status', 'paid')->whereMonth('paid_date', $currentMonth)->whereYear('paid_date', $currentYear)->sum('amount_cents'),
        ];

        $categoryOptions = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $supplierOptions = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $customerOptions = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('finance/transactions/index', [
            'obligations' => $obligations,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'customer_id' => $customerId,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],
            'categoryOptions' => $categoryOptions,
            'supplierOptions' => $supplierOptions,
            'customerOptions' => $customerOptions,
        ]);
    }

    public function show(FinancialObligation $financialObligation, TenantContext $context): Response
    {
        Gate::authorize('view', $financialObligation);

        if ($financialObligation->tenant_id !== $context->tenant->getKey() || $financialObligation->unit_id !== $context->unit?->getKey()) {
            abort(404);
        }

        return Inertia::render('finance/transactions/show', [
            'obligation' => $financialObligation->load(['category:id,name', 'supplier:id,name', 'customer:id,name']),
        ]);
    }

    public function store(FinancialObligationRequest $request, TenantContext $context, CreateFinancialObligation $createObligation): RedirectResponse
    {
        $data = $request->validated();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createObligation, $request, $context, $data): array {
            $obligation = $createObligation->handle($request->user(), $context, $data);

            return ['resource_id' => $obligation->getKey(), 'resource_type' => 'financial_obligation'];
        });

        return back()->with('success', 'Lançamento financeiro registrado com sucesso.');
    }

    public function update(FinancialObligationRequest $request, TenantContext $context, FinancialObligation $financialObligation, UpdateFinancialObligation $updateObligation): RedirectResponse
    {
        $data = $request->validated();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateObligation, $request, $context, $financialObligation, $data): array {
            $updated = $updateObligation->handle($request->user(), $context, $financialObligation, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'financial_obligation'];
        });

        return back()->with('success', 'Lançamento financeiro atualizado com sucesso.');
    }

    public function settle(SettleObligationRequest $request, TenantContext $context, FinancialObligation $financialObligation, SettleFinancialObligation $settleObligation): RedirectResponse
    {
        $data = $request->validated();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($settleObligation, $request, $context, $financialObligation, $data): array {
            $settled = $settleObligation->handle($request->user(), $context, $financialObligation, $data);

            return ['resource_id' => $settled->getKey(), 'resource_type' => 'financial_obligation'];
        });

        return back()->with('success', 'Lançamento liquidado com sucesso.');
    }

    public function cancel(Request $request, TenantContext $context, FinancialObligation $financialObligation, CancelFinancialObligation $cancelObligation): RedirectResponse
    {
        Gate::authorize('cancel', $financialObligation);

        $request->validate([
            'lock_version' => ['required', 'integer'],
        ]);

        $data = ['lock_version' => (int) $request->input('lock_version')];

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($cancelObligation, $request, $context, $financialObligation, $data): array {
            $cancelled = $cancelObligation->handle($request->user(), $context, $financialObligation, $data);

            return ['resource_id' => $cancelled->getKey(), 'resource_type' => 'financial_obligation'];
        });

        return back()->with('success', 'Lançamento financeiro cancelado com sucesso.');
    }
}
