<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\FinancialObligation;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FinanceDashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', FinancialObligation::class);

        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        $today = now()->toDateString();
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // Current operational cash in open cash shifts
        $currentCashBalanceCents = (int) CashShift::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'open')
            ->sum('expected_amount_cents');

        $baseObligationsQuery = FinancialObligation::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId);

        // Today metrics
        $payableTodayCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')
            ->where('status', 'pending')
            ->whereDate('due_date', $today)
            ->sum('amount_cents');

        $receivableTodayCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'receivable')
            ->where('status', 'pending')
            ->whereDate('due_date', $today)
            ->sum('amount_cents');

        // Month metrics
        $payableMonthPendingCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')
            ->where('status', 'pending')
            ->whereMonth('due_date', $currentMonth)
            ->whereYear('due_date', $currentYear)
            ->sum('amount_cents');

        $receivableMonthPendingCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'receivable')
            ->where('status', 'pending')
            ->whereMonth('due_date', $currentMonth)
            ->whereYear('due_date', $currentYear)
            ->sum('amount_cents');

        $paidMonthCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'payable')
            ->where('status', 'paid')
            ->whereMonth('paid_date', $currentMonth)
            ->whereYear('paid_date', $currentYear)
            ->sum('amount_cents');

        $receivedMonthCents = (int) (clone $baseObligationsQuery)
            ->where('type', 'receivable')
            ->where('status', 'paid')
            ->whereMonth('paid_date', $currentMonth)
            ->whereYear('paid_date', $currentYear)
            ->sum('amount_cents');

        // Overdue metrics
        $overdueQuery = (clone $baseObligationsQuery)
            ->where('status', 'pending')
            ->whereDate('due_date', '<', $today);

        $overdueCount = $overdueQuery->count();
        $overdueAmountCents = (int) $overdueQuery->sum('amount_cents');

        // Projected balance
        $projectedBalanceCents = $currentCashBalanceCents + $receivableMonthPendingCents - $payableMonthPendingCents;

        // Upcoming due obligations
        $upcomingObligations = (clone $baseObligationsQuery)
            ->with(['category:id,name', 'supplier:id,name', 'customer:id,name'])
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $today)
            ->orderBy('due_date')
            ->take(8)
            ->get();

        // Recent settled obligations
        $recentSettled = (clone $baseObligationsQuery)
            ->with(['category:id,name', 'supplier:id,name', 'customer:id,name'])
            ->where('status', 'paid')
            ->orderByDesc('paid_date')
            ->orderByDesc('updated_at')
            ->take(8)
            ->get();

        return Inertia::render('finance/dashboard', [
            'metrics' => [
                'current_cash_balance_cents' => $currentCashBalanceCents,
                'projected_balance_cents' => $projectedBalanceCents,
                'payable_today_cents' => $payableTodayCents,
                'receivable_today_cents' => $receivableTodayCents,
                'payable_month_pending_cents' => $payableMonthPendingCents,
                'receivable_month_pending_cents' => $receivableMonthPendingCents,
                'paid_month_cents' => $paidMonthCents,
                'received_month_cents' => $receivedMonthCents,
                'overdue_count' => $overdueCount,
                'overdue_amount_cents' => $overdueAmountCents,
            ],
            'upcomingObligations' => $upcomingObligations,
            'recentSettled' => $recentSettled,
        ]);
    }
}
