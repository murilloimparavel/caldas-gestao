<?php

namespace App\Http\Controllers;

use App\Actions\Finance\Commissions\DeleteCommissionRule;
use App\Actions\Finance\Commissions\SaveCommissionRule;
use App\Actions\Finance\Commissions\SettleCommissions;
use App\Http\Requests\CommissionRuleRequest;
use App\Http\Requests\CommissionSettlementRequest;
use App\Models\CommissionAccrual;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Service;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CommissionController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', CommissionRule::class);

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();

        $professionals = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Calculate commission statistics per professional
        $accrualStats = CommissionAccrual::query()
            ->select([
                'professional_id',
                DB::raw("SUM(CASE WHEN status = 'accrued' THEN commission_amount_cents ELSE 0 END) as pending_amount_cents"),
                DB::raw("SUM(CASE WHEN status = 'settled' THEN commission_amount_cents ELSE 0 END) as settled_amount_cents"),
                DB::raw("COUNT(CASE WHEN status = 'accrued' THEN 1 ELSE NULL END) as pending_count"),
            ])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->groupBy('professional_id')
            ->get()
            ->keyBy('professional_id');

        $professionalsSummary = $professionals->map(function (Professional $prof) use ($accrualStats): array {
            $stats = $accrualStats->get($prof->getKey());

            return [
                'id' => $prof->getKey(),
                'name' => $prof->name,
                'email' => $prof->email,
                'phone' => $prof->phone,
                'pending_amount_cents' => (int) ($stats->pending_amount_cents ?? 0),
                'settled_amount_cents' => (int) ($stats->settled_amount_cents ?? 0),
                'pending_count' => (int) ($stats->pending_count ?? 0),
            ];
        });

        $rules = CommissionRule::query()
            ->with(['professional', 'service', 'product'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->orderByDesc('created_at')
            ->get();

        $services = Service::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents']);

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price_cents']);

        $totalPendingCents = (int) CommissionAccrual::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'accrued')
            ->sum('commission_amount_cents');

        $totalSettledThisMonthCents = (int) CommissionSettlement::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total_amount_cents');

        return Inertia::render('finance/commissions/index', [
            'professionals' => $professionalsSummary,
            'rules' => $rules,
            'services' => $services,
            'products' => $products,
            'metrics' => [
                'total_pending_cents' => $totalPendingCents,
                'total_settled_month_cents' => $totalSettledThisMonthCents,
                'rules_count' => $rules->count(),
            ],
        ]);
    }

    public function show(Professional $professional, Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', CommissionRule::class);

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();

        if ($professional->tenant_id !== $tenantId || $professional->unit_id !== $unitId) {
            abort(404);
        }

        $status = trim((string) $request->string('status'));
        $dateStart = trim((string) $request->string('date_start'));
        $dateEnd = trim((string) $request->string('date_end'));

        $accrualsQuery = CommissionAccrual::query()
            ->with(['sale', 'settlement'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professional->getKey())
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dateStart !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateStart))
            ->when($dateEnd !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateEnd))
            ->orderByDesc('created_at');

        $accruals = $accrualsQuery->paginate(25)->withQueryString();

        $settlements = CommissionSettlement::query()
            ->with(['user'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professional->getKey())
            ->orderByDesc('paid_at')
            ->take(10)
            ->get();

        $pendingAmountCents = (int) CommissionAccrual::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professional->getKey())
            ->where('status', 'accrued')
            ->sum('commission_amount_cents');

        $totalSettledCents = (int) CommissionAccrual::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('professional_id', $professional->getKey())
            ->where('status', 'settled')
            ->sum('commission_amount_cents');

        return Inertia::render('finance/commissions/show', [
            'professional' => $professional,
            'accruals' => $accruals,
            'settlements' => $settlements,
            'filters' => [
                'status' => $status,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],
            'metrics' => [
                'pending_amount_cents' => $pendingAmountCents,
                'total_settled_cents' => $totalSettledCents,
                'total_accruals_count' => CommissionAccrual::query()
                    ->where('tenant_id', $tenantId)
                    ->where('unit_id', $unitId)
                    ->where('professional_id', $professional->getKey())
                    ->count(),
            ],
        ]);
    }

    public function storeRule(CommissionRuleRequest $request, TenantContext $context, SaveCommissionRule $saveRule): RedirectResponse
    {
        $data = $request->validated();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($saveRule, $request, $context, $data): array {
            $rule = $saveRule->handle($request->user(), $context, $data);

            return ['resource_id' => $rule->getKey(), 'resource_type' => 'commission_rule'];
        });

        return back()->with('success', 'Regra de comissão salva com sucesso.');
    }

    public function updateRule(CommissionRuleRequest $request, TenantContext $context, CommissionRule $rule, SaveCommissionRule $saveRule): RedirectResponse
    {
        $data = $request->validated();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($saveRule, $request, $context, $rule, $data): array {
            $updatedRule = $saveRule->handle($request->user(), $context, $data, $rule);

            return ['resource_id' => $updatedRule->getKey(), 'resource_type' => 'commission_rule'];
        });

        return back()->with('success', 'Regra de comissão atualizada com sucesso.');
    }

    public function deleteRule(Request $request, TenantContext $context, CommissionRule $rule, DeleteCommissionRule $deleteRule): RedirectResponse
    {
        Gate::authorize('delete', $rule);

        $deleteRule->handle($request->user(), $context, $rule);

        return back()->with('success', 'Regra de comissão excluída com sucesso.');
    }

    public function settle(CommissionSettlementRequest $request, TenantContext $context, SettleCommissions $settleCommissions): RedirectResponse
    {
        $data = $request->validated();
        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        /** @var Professional $professional */
        $professional = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->whereKey($data['professional_id'])
            ->firstOrFail();

        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($settleCommissions, $request, $context, $professional, $data): array {
            $settlement = $settleCommissions->handle($request->user(), $context, $professional, $data);

            return ['resource_id' => $settlement->getKey(), 'resource_type' => 'commission_settlement'];
        });

        return back()->with('success', 'Comissões liquidadas com sucesso.');
    }
}
