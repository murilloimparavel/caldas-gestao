<?php

namespace App\Http\Controllers;

use App\Actions\Sales\ApplySaleDiscount;
use App\Actions\Sales\OpenSale;
use App\Actions\Sales\TransitionSaleStatus;
use App\Http\Requests\OpenSaleRequest;
use App\Http\Requests\SaleDiscountRequest;
use App\Http\Requests\SaleStatusTransitionRequest;
use App\Models\Sale;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SaleController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Sale::class);

        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $customerId = trim((string) $request->string('customer_id'));
        $saleCategoryId = trim((string) $request->string('sale_category_id'));

        $sales = Sale::query()
            ->with(['customer', 'category', 'appointmentLink.appointment'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('reference_label', 'like', "%{$search}%")
                        ->orWhere('category_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($customerId !== '', fn ($query) => $query->where('customer_id', $customerId))
            ->when($saleCategoryId !== '', fn ($query) => $query->where('sale_category_id', $saleCategoryId))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('sales/index', [
            'sales' => $sales,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'customer_id' => $customerId,
                'sale_category_id' => $saleCategoryId,
            ],
        ]);
    }

    public function show(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        $sale->load([
            'items.service',
            'items.product',
            'items.professional',
            'customer',
            'category',
            'appointmentLink.appointment',
            'statusHistories.user',
        ]);

        return Inertia::render('sales/show', [
            'sale' => $sale,
        ]);
    }

    public function store(OpenSaleRequest $request, TenantContext $context, OpenSale $openSale): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($openSale, $request, $context, $data): array {
            $sale = $openSale->handle($request->user(), $context, $data);

            return ['resource_id' => $sale->getKey(), 'resource_type' => 'sale'];
        });

        $sale = Sale::query()->findOrFail($reference['resource_id']);

        return to_route('sales.show', $sale)->with('success', 'Comanda aberta com sucesso.');
    }

    public function applyDiscount(SaleDiscountRequest $request, TenantContext $context, Sale $sale, ApplySaleDiscount $applySaleDiscount): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($applySaleDiscount, $request, $context, $sale, $data): array {
            $updated = $applySaleDiscount->handle($request->user(), $context, $sale, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'sale'];
        });

        $updatedSale = Sale::query()->findOrFail($reference['resource_id']);

        return to_route('sales.show', $updatedSale)->with('success', 'Desconto aplicado com sucesso.');
    }

    public function transitionStatus(SaleStatusTransitionRequest $request, TenantContext $context, Sale $sale, TransitionSaleStatus $transitionSaleStatus): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($transitionSaleStatus, $request, $context, $sale, $data): array {
            $updated = $transitionSaleStatus->handle(
                $request->user(),
                $context,
                $sale,
                (string) $data['status'],
                isset($data['reason']) ? (string) $data['reason'] : null,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null,
            );

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'sale'];
        });

        $updatedSale = Sale::query()->findOrFail($reference['resource_id']);

        return to_route('sales.show', $updatedSale)->with('success', 'Status da comanda atualizado com sucesso.');
    }
}
