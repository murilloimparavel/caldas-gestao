<?php

namespace App\Http\Controllers;

use App\Actions\Sales\AdjustSale;
use App\Actions\Sales\ApplySaleDiscount;
use App\Actions\Sales\OpenSale;
use App\Actions\Sales\TransitionSaleStatus;
use App\Http\Requests\AdjustSaleRequest;
use App\Http\Requests\OpenSaleRequest;
use App\Http\Requests\SaleDiscountRequest;
use App\Http\Requests\SaleStatusTransitionRequest;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackageUsageReservation;
use App\Models\Product;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\SaleCategory;
use App\Models\Service;
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

        $unitId = $context->unit?->getKey();
        $tenantId = $context->tenant->getKey();
        $unitTimezone = $context->unit === null ? config('app.timezone') : ($context->unit->timezone ?? config('app.timezone'));

        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $customerId = trim((string) $request->string('customer_id'));
        $saleCategoryId = trim((string) $request->string('sale_category_id'));

        $sales = Sale::query()
            ->with(['customer', 'category', 'appointmentLink.appointment'])
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
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

        $categories = SaleCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'uniqueness_scope']);

        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $todayStart = now($unitTimezone)->startOfDay();

        $metrics = [
            'open_count' => Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'open')
                ->count(),
            'ready_count' => Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'ready_to_bill')
                ->count(),
            'today_total_cents' => (int) Sale::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereIn('status', ['open', 'ready_to_bill'])
                ->where('created_at', '>=', $todayStart)
                ->sum('final_amount_cents'),
        ];

        return Inertia::render('sales/index', [
            'active_cash_shift' => CashShift::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('opened_by_user_id', $request->user()->getKey())->where('status', 'open')->latest('opened_at')->first(),
            'sales' => $sales,
            'categories' => $categories,
            'customers' => $customers,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'customer_id' => $customerId,
                'sale_category_id' => $saleCategoryId,
            ],
        ]);
    }

    public function show(Request $request, Sale $sale, TenantContext $context): Response
    {
        Gate::authorize('view', $sale);

        $unitId = $context->unit?->getKey() ?? $sale->unit_id;
        $tenantId = $context->tenant->getKey();
        $currentSaleId = $sale->getKey();

        $sale->load([
            'items.service',
            'items.product',
            'items.professional',
            'items.sellerProfessional',
            'customer',
            'category',
            'appointmentLink.appointment',
            'statusHistories.user',
            'closingSessions',
        ]);

        $customerPackages = collect();
        if ($sale->customer_id !== null) {
            $packages = CustomerPackage::query()
                ->with(['serviceBalances.service', 'packageTemplate'])
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('customer_id', $sale->customer_id)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
                })
                ->where('remaining_sessions', '>', 0)
                ->orderBy('expires_at')
                ->get();
            $reservationsByPackage = PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereIn('customer_package_id', $packages->modelKeys())
                ->where('status', 'reserved')
                ->get(['customer_package_id', 'sale_id', 'service_id', 'sessions_reserved'])
                ->groupBy('customer_package_id');

            $customerPackages = $packages
                ->map(function (CustomerPackage $package) use ($currentSaleId, $reservationsByPackage): array {
                    $packageReservations = $reservationsByPackage->get($package->getKey(), collect());
                    $serviceReservations = $packageReservations->groupBy('service_id');
                    $currentSaleReservations = $packageReservations->where('sale_id', $currentSaleId);
                    $currentSaleServiceReservations = $currentSaleReservations->groupBy('service_id');
                    $packageReserved = (int) $packageReservations->sum('sessions_reserved');
                    $currentSaleReserved = (int) $currentSaleReservations->sum('sessions_reserved');
                    $currentSaleReservedByService = $currentSaleServiceReservations
                        ->map(static fn ($reservations): int => (int) $reservations->sum('sessions_reserved'));
                    $otherSaleReserved = max(0, $packageReserved - $currentSaleReserved);

                    return [
                        'id' => $package->getKey(),
                        'name' => $package->name_snapshot ?? $package->packageTemplate?->name ?? 'Pacote de serviços',
                        'remaining_sessions' => (int) $package->remaining_sessions,
                        'reserved_sessions' => $packageReserved,
                        'other_reserved_sessions' => $otherSaleReserved,
                        'current_sale_reserved_sessions' => $currentSaleReserved,
                        'available_sessions' => max(0, (int) $package->remaining_sessions - $packageReserved),
                        'expires_at' => $package->expires_at?->toDateString(),
                        'services' => $package->serviceBalances->map(fn ($balance): array => [
                            'id' => $balance->service_id,
                            'name' => $balance->service?->name ?? 'Serviço',
                            'allocated' => (int) $balance->allocated_quantity,
                            'remaining' => (int) $balance->remaining_quantity,
                            'reserved' => (int) $serviceReservations->get($balance->service_id, collect())->sum('sessions_reserved'),
                            'other_reserved' => max(0, (int) $serviceReservations->get($balance->service_id, collect())->sum('sessions_reserved') - (int) ($currentSaleReservedByService[$balance->service_id] ?? 0)),
                            'current_sale_reserved' => (int) ($currentSaleReservedByService[$balance->service_id] ?? 0),
                            'available' => max(0, (int) $balance->remaining_quantity - (int) $serviceReservations->get($balance->service_id, collect())->sum('sessions_reserved')),
                        ])->values(),
                    ];
                })
                ->values();
        }

        $services = Service::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents', 'duration_minutes']);

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sale_price_cents', 'current_stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'price_cents' => $product->sale_price_cents,
                'sale_price_cents' => $product->sale_price_cents,
                'current_stock' => $product->current_stock,
            ])
            ->values();

        $professionals = Professional::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = SaleCategory::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'uniqueness_scope']);

        return Inertia::render('sales/show', [
            'active_cash_shift' => CashShift::query()->where('tenant_id', $tenantId)->where('unit_id', $unitId)->where('opened_by_user_id', $request->user()->getKey())->where('status', 'open')->latest('opened_at')->first(),
            'sale' => $sale,
            'services' => $services,
            'products' => $products,
            'professionals' => $professionals,
            'categories' => $categories,
            'customerPackages' => $customerPackages,
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

    public function adjust(AdjustSaleRequest $request, TenantContext $context, Sale $sale, AdjustSale $adjustSale): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($adjustSale, $request, $context, $sale, $data): array {
            $updated = $adjustSale->handle(
                $request->user(),
                $context,
                $sale,
                (string) $data['reason'],
                isset($data['lock_version']) ? (int) $data['lock_version'] : null,
            );

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'sale'];
        });

        $updatedSale = Sale::query()->findOrFail($reference['resource_id']);

        return to_route('sales.show', $updatedSale)->with('success', 'Comanda estornada com sucesso.');
    }
}
