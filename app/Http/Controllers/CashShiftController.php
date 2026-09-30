<?php

namespace App\Http\Controllers;

use App\Actions\Finance\Cash\CloseCashShift;
use App\Actions\Finance\Cash\OpenCashShift;
use App\Actions\Finance\Cash\RecordCashMovement;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseCashShiftRequest;
use App\Http\Requests\OpenCashShiftRequest;
use App\Models\CashShift;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CashShiftController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', CashShift::class);

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();
        $user = $request->user();

        /** @var CashShift|null $activeShift */
        $activeShift = CashShift::query()
            ->with(['openedBy', 'closedBy', 'movements.user'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('opened_by_user_id', $user->getKey())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $openShiftsCount = CashShift::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'open')
            ->count();

        $closedTodayCount = CashShift::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'closed')
            ->whereDate('closed_at', now()->toDateString())
            ->count();

        return Inertia::render('finance/cash/index', [
            'active_shift' => $activeShift,
            'metrics' => [
                'open_shifts_count' => $openShiftsCount,
                'closed_today_count' => $closedTodayCount,
            ],
        ]);
    }

    public function show(CashShift $cashShift, TenantContext $context): Response
    {
        Gate::authorize('view', $cashShift);

        $cashShift->load(['openedBy', 'closedBy', 'movements.user']);

        return Inertia::render('finance/cash/show', [
            'shift' => $cashShift,
        ]);
    }

    public function store(OpenCashShiftRequest $request, TenantContext $context, OpenCashShift $openCashShift): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($openCashShift, $request, $context, $data): array {
            $shift = $openCashShift->handle($request->user(), $context, $data);

            return ['resource_id' => $shift->getKey(), 'resource_type' => 'cash_shift'];
        });

        return isset($data['return_to']) && $data['return_to'] === 'sales'
            ? to_route('sales.index')->with('success', 'Caixa aberto com sucesso. Continue o recebimento da comanda.')
            : to_route('cash_shifts.index')->with('success', 'Caixa aberto com sucesso.');
    }

    public function move(CashMovementRequest $request, TenantContext $context, CashShift $cashShift, RecordCashMovement $recordCashMovement): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($recordCashMovement, $request, $context, $cashShift, $data): array {
            $movement = $recordCashMovement->handle($request->user(), $context, $cashShift, $data);

            return ['resource_id' => $movement->getKey(), 'resource_type' => 'cash_movement'];
        });

        return to_route('cash_shifts.index')->with('success', 'Movimentação registrada com sucesso.');
    }

    public function close(CloseCashShiftRequest $request, TenantContext $context, CashShift $cashShift, CloseCashShift $closeCashShift): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($closeCashShift, $request, $context, $cashShift, $data): array {
            $shift = $closeCashShift->handle($request->user(), $context, $cashShift, $data);

            return ['resource_id' => $shift->getKey(), 'resource_type' => 'cash_shift'];
        });

        return to_route('cash_shifts.index')->with('success', 'Caixa fechado com sucesso.');
    }

    public function history(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', CashShift::class);

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();

        $status = trim((string) $request->string('status'));
        $date = trim((string) $request->string('date'));

        $shifts = CashShift::query()
            ->with(['openedBy', 'closedBy', 'movements'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($date !== '', fn ($query) => $query->whereDate('opened_at', $date))
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('finance/cash/history', [
            'shifts' => $shifts,
            'filters' => [
                'status' => $status,
                'date' => $date,
            ],
        ]);
    }
}
