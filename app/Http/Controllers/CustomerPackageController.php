<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Packages\ArchiveCustomerPackage;
use App\Actions\Marketing\Packages\CancelCustomerPackage;
use App\Actions\Marketing\Packages\ConsumePackageSession;
use App\Actions\Marketing\Packages\RestoreCustomerPackage;
use App\Actions\Marketing\Packages\ReversePackageUsage;
use App\Actions\Marketing\Packages\StartCustomerPackageSale;
use App\Http\Requests\ArchiveCustomerPackageRequest;
use App\Http\Requests\CancelCustomerPackageRequest;
use App\Http\Requests\ConsumePackageSessionRequest;
use App\Http\Requests\CustomerPackageHistoryRequest;
use App\Http\Requests\RestoreCustomerPackageRequest;
use App\Http\Requests\ReversePackageUsageRequest;
use App\Http\Requests\SellCustomerPackageRequest;
use App\Models\ClosingSession;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\PackageUsage;
use App\Models\PackageUsageReservation;
use App\Models\Sale;
use App\Support\LegacyPackageReconciliation;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerPackageController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function archived(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', CustomerPackage::class);

        $search = trim((string) $request->string('search'));
        $canViewFinance = Gate::allows('viewAny', FinancialObligation::class)
            && FinancialObligation::hasCustomerPackageLink();

        $packages = CustomerPackage::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'archived')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches->where('name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('packageTemplate', fn ($template) => $template->where('name', 'like', "%{$search}%"));
                });
            })
            ->with([
                'customer:id,name,phone',
                'packageTemplate:id,name',
                'serviceBalances.service:id,name',
                'usages.user:id,name',
                'usages.service:id,name',
            ])
            ->when($canViewFinance, fn ($query) => $query->with('financialObligation:id,customer_package_id,amount_cents,status,paid_date,payment_method'))
            ->orderByDesc('archived_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('packages/archived', [
            'packages' => $packages,
            'filters' => ['search' => $search],
            'canManage' => Gate::allows('manageAny', CustomerPackage::class),
            'canViewFinance' => $canViewFinance,
        ]);
    }

    public function archive(ArchiveCustomerPackageRequest $request, TenantContext $context, CustomerPackage $customer_package, ArchiveCustomerPackage $archiveCustomerPackage): RedirectResponse
    {
        $this->mutation->execute($request, $context, $request->user(), [], function () use ($archiveCustomerPackage, $request, $context, $customer_package): array {
            $package = $archiveCustomerPackage->handle($request->user(), $context, $customer_package);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Pacote arquivado. Ele continua disponível na tela de pacotes arquivados.');
    }

    public function restore(RestoreCustomerPackageRequest $request, TenantContext $context, CustomerPackage $customer_package, RestoreCustomerPackage $restoreCustomerPackage): RedirectResponse
    {
        $this->mutation->execute($request, $context, $request->user(), [], function () use ($restoreCustomerPackage, $request, $context, $customer_package): array {
            $package = $restoreCustomerPackage->handle($request->user(), $context, $customer_package);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Pacote restaurado para a lista do cliente.');
    }

    public function store(SellCustomerPackageRequest $request, TenantContext $context, StartCustomerPackageSale $startCustomerPackageSale): RedirectResponse
    {
        $data = $request->validated();
        $data['start_sale'] = true;
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        $sourceSuffix = $idempotencyKey !== '' ? substr(hash('sha256', $idempotencyKey), 0, 40) : null;
        $data = [
            ...$data,
            'sale_source_id' => $sourceSuffix !== null ? "package-sale:{$sourceSuffix}" : null,
            'item_source_id' => $sourceSuffix !== null ? "package-item:{$sourceSuffix}" : null,
        ];

        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($startCustomerPackageSale, $request, $context, $data): array {
            $sale = $startCustomerPackageSale->handle($request->user(), $context, $data);

            return ['resource_id' => $sale->getKey(), 'resource_type' => 'sale'];
        });

        return to_route('sales.show', $reference['resource_id'])->with('success', 'Comanda aberta com o pacote. Escolha a forma de pagamento ao fechar.');
    }

    public function consume(ConsumePackageSessionRequest $request, TenantContext $context, CustomerPackage $customer_package, ConsumePackageSession $consumePackageSession): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($consumePackageSession, $request, $context, $customer_package, $data): array {
            $package = $consumePackageSession->handle($request->user(), $context, $customer_package, $data);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Sessão consumida com sucesso.');
    }

    public function cancel(CancelCustomerPackageRequest $request, TenantContext $context, CustomerPackage $customer_package, CancelCustomerPackage $cancelCustomerPackage): RedirectResponse
    {
        /** @var array{reason: string} $data */
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($cancelCustomerPackage, $request, $context, $customer_package, $data): array {
            $package = $cancelCustomerPackage->handle($request->user(), $context, $customer_package, $data);

            return ['resource_id' => $package->getKey(), 'resource_type' => 'customer_package'];
        });

        return back()->with('success', 'Pacote pendente cancelado.');
    }

    public function reverseUsage(ReversePackageUsageRequest $request, TenantContext $context, CustomerPackage $customer_package, PackageUsage $package_usage, ReversePackageUsage $reversePackageUsage): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reversePackageUsage, $request, $context, $package_usage, $data): array {
            $usage = $reversePackageUsage->handle($request->user(), $context, $package_usage, $data);

            return ['resource_id' => $usage->getKey(), 'resource_type' => 'package_usage'];
        });

        return back()->with('success', 'Consumo revertido com sucesso.');
    }

    public function history(CustomerPackageHistoryRequest $request, TenantContext $context, CustomerPackage $customer_package): Response
    {
        Gate::authorize('view', $customer_package);

        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        if (
            $customer_package->tenant_id !== $tenantId
            || ($unitId !== null && $customer_package->unit_id !== $unitId)
        ) {
            abort(404);
        }

        $customer_package->load([
            'customer:id,name,phone',
            'packageTemplate:id,name',
            'serviceBalances.service:id,name',
        ]);

        $filters = $request->validated();
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $serviceId = $filters['service_id'] ?? '';
        $status = $filters['status'] ?? 'all';

        $filteredQuery = PackageUsage::query()
            ->where('tenant_id', $tenantId)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->where('customer_package_id', $customer_package->getKey())
            ->when($from !== null, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($serviceId !== '', fn ($query) => $query->where('service_id', $serviceId))
            ->when($status === 'consumed', fn ($query) => $query->whereNull('reversed_at'))
            ->when($status === 'reversed', fn ($query) => $query->whereNotNull('reversed_at'));

        $totals = [
            'records' => (clone $filteredQuery)->count(),
            'sessions' => (int) (clone $filteredQuery)->sum('sessions_consumed'),
            'consumed_sessions' => (int) (clone $filteredQuery)->whereNull('reversed_at')->sum('sessions_consumed'),
            'reversed_sessions' => (int) (clone $filteredQuery)->whereNotNull('reversed_at')->sum('sessions_consumed'),
        ];

        $serviceTotals = (clone $filteredQuery)
            ->select('service_id')
            ->selectRaw('SUM(sessions_consumed) AS sessions')
            ->selectRaw('SUM(CASE WHEN reversed_at IS NULL THEN sessions_consumed ELSE 0 END) AS consumed_sessions')
            ->selectRaw('SUM(CASE WHEN reversed_at IS NOT NULL THEN sessions_consumed ELSE 0 END) AS reversed_sessions')
            ->with('service:id,name')
            ->groupBy('service_id')
            ->get()
            ->map(fn (PackageUsage $usage): array => [
                'id' => $usage->service_id,
                'name' => $usage->service?->name ?? 'Serviço não informado',
                'sessions' => (int) $usage->getAttribute('sessions'),
                'consumed_sessions' => (int) $usage->getAttribute('consumed_sessions'),
                'reversed_sessions' => (int) $usage->getAttribute('reversed_sessions'),
            ])
            ->sortBy('name')
            ->values();

        $usages = $filteredQuery
            ->with([
                'service:id,name',
                'user:id,name',
                'reversedByUser:id,name',
                'sale' => function ($query) use ($tenantId, $unitId): void {
                    $query
                        ->select(['id', 'tenant_id', 'unit_id', 'reference_label', 'status', 'customer_id', 'created_at'])
                        ->where('tenant_id', $tenantId)
                        ->when($unitId !== null, fn ($saleQuery) => $saleQuery->where('unit_id', $unitId));
                },
                'sale.appointmentLink.appointment:id,starts_at,ends_at,status,professional_id',
                'sale.appointmentLink.appointment.professional:id,name',
                'sale.closingSessions:id,status,created_at,closed_by_user_id',
                'sale.closingSessions.closedBy:id,name',
                'saleItem' => function ($query) use ($tenantId, $unitId): void {
                    $query
                        ->select(['id', 'tenant_id', 'unit_id', 'sale_id', 'service_id', 'professional_id', 'name_snapshot', 'quantity', 'covered_quantity'])
                        ->where('tenant_id', $tenantId)
                        ->when($unitId !== null, fn ($itemQuery) => $itemQuery->where('unit_id', $unitId));
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(static function (PackageUsage $usage): array {
                $appointment = $usage->sale?->appointmentLink?->appointment;
                $closingSessions = ($usage->sale?->closingSessions ?? collect())
                    ->where('status', 'completed')
                    ->sortByDesc('created_at')
                    ->values();

                return [
                    'id' => $usage->getKey(),
                    'consumed_at' => $usage->created_at?->toIso8601String(),
                    'sessions_consumed' => $usage->sessions_consumed,
                    'status' => $usage->reversed_at === null ? 'consumed' : 'reversed',
                    'service' => $usage->service === null ? null : [
                        'id' => $usage->service->getKey(),
                        'name' => $usage->service->name,
                    ],
                    'user' => $usage->user === null ? null : [
                        'id' => $usage->user->getKey(),
                        'name' => $usage->user->name,
                    ],
                    'reversed_at' => $usage->reversed_at?->toIso8601String(),
                    'reversed_by_user' => $usage->reversedByUser === null ? null : [
                        'id' => $usage->reversedByUser->getKey(),
                        'name' => $usage->reversedByUser->name,
                    ],
                    'reversal_reason' => $usage->reversal_reason,
                    'sale' => $usage->sale === null ? null : [
                        'id' => $usage->sale->getKey(),
                        'reference_label' => $usage->sale->reference_label,
                        'status' => $usage->sale->status,
                        'created_at' => $usage->sale->created_at?->toIso8601String(),
                    ],
                    'sale_item' => $usage->saleItem === null ? null : [
                        'id' => $usage->saleItem->getKey(),
                        'name_snapshot' => $usage->saleItem->name_snapshot,
                        'quantity' => $usage->saleItem->quantity,
                        'covered_quantity' => $usage->saleItem->covered_quantity,
                    ],
                    'origin' => $appointment !== null
                        ? [
                            'type' => 'appointment',
                            'label' => 'Agendamento',
                            'appointment' => [
                                'id' => $appointment->getKey(),
                                'starts_at' => $appointment->starts_at?->toIso8601String(),
                                'ends_at' => $appointment->ends_at?->toIso8601String(),
                                'status' => $appointment->status,
                                'professional' => $appointment->professional === null ? null : [
                                    'id' => $appointment->professional->getKey(),
                                    'name' => $appointment->professional->name,
                                ],
                            ],
                        ]
                        : ($usage->sale !== null
                            ? [
                                'type' => 'sale',
                                'label' => 'Comanda sem agendamento',
                            ]
                            : [
                                'type' => 'none',
                                'label' => 'Sem origem vinculada',
                            ]),
                    'closings' => $closingSessions
                        ->map(static fn (ClosingSession $closingSession): array => [
                            'id' => $closingSession->getKey(),
                            'status' => $closingSession->status,
                            'recorded_at' => $closingSession->created_at?->toIso8601String(),
                            'closed_by' => $closingSession->closedBy === null ? null : [
                                'id' => $closingSession->closedBy->getKey(),
                                'name' => $closingSession->closedBy->name,
                            ],
                        ])
                        ->all(),
                ];
            });

        $serviceOptions = $customer_package->serviceBalances
            ->filter(fn ($balance): bool => $balance->service !== null)
            ->map(fn ($balance): array => [
                'id' => $balance->service->getKey(),
                'name' => $balance->service->name,
            ])
            ->values();

        $usageServiceOptions = PackageUsage::query()
            ->where('tenant_id', $tenantId)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->where('customer_package_id', $customer_package->getKey())
            ->whereNotNull('service_id')
            ->with('service:id,name')
            ->get(['service_id'])
            ->filter(fn (PackageUsage $usage): bool => $usage->service !== null)
            ->map(fn (PackageUsage $usage): array => [
                'id' => $usage->service->getKey(),
                'name' => $usage->service->name,
            ]);

        $serviceOptions = $serviceOptions
            ->concat($usageServiceOptions)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return Inertia::render('customer-packages/history', [
            'customerPackage' => [
                'id' => $customer_package->getKey(),
                'name' => $customer_package->name_snapshot ?: ($customer_package->packageTemplate?->name ?? 'Pacote de serviços'),
                'status' => $customer_package->status,
                'total_sessions' => $customer_package->total_sessions,
                'remaining_sessions' => $customer_package->remaining_sessions,
                'created_at' => $customer_package->created_at?->toIso8601String(),
                'expires_at' => $customer_package->expires_at?->toDateString(),
                'customer' => [
                    'id' => $customer_package->customer->getKey(),
                    'name' => $customer_package->customer->name,
                    'phone' => $customer_package->customer->phone,
                ],
            ],
            'usages' => $usages,
            'totals' => $totals,
            'serviceTotals' => $serviceTotals,
            'services' => $serviceOptions,
            'filters' => [
                'from' => $from ?? '',
                'to' => $to ?? '',
                'service_id' => $serviceId,
                'status' => $status,
            ],
            'canViewSales' => Gate::allows('viewAny', Sale::class),
            'canViewClosingSessions' => Gate::allows('viewAny', ClosingSession::class),
        ]);
    }

    public function activeForCustomer(Request $request, TenantContext $context, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $tenantId = $context->tenant->getKey();
        $unitId = $context->unit?->getKey();

        if ($customer->tenant_id !== $tenantId || ($unitId !== null && $customer->unit_id !== $unitId)) {
            abort(404);
        }

        $today = CarbonImmutable::now()->toDateString();

        $packages = CustomerPackage::query()
            ->where('tenant_id', $tenantId)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->where('customer_id', $customer->getKey())
            ->where('status', 'active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $today);
            })
            ->where('remaining_sessions', '>', 0)
            ->with([
                'packageTemplate:id,name',
                'serviceBalances.service:id,name',
                'sale.closingSessions.payments',
                'financialObligation',
            ])
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->get();

        $packageEvidence = new LegacyPackageReconciliation;
        $financialLinkAvailable = Schema::hasColumn('financial_obligations', 'customer_package_id');

        $activePackages = [];

        foreach ($packages as $package) {
            $linkedItems = $package->sale !== null
                ? $package->sale->items()->where('customer_package_id', $package->getKey())->with('sale.closingSessions.payments')->get()
                : collect();

            if (! $packageEvidence->hasStrictPaidSaleEvidence($package, $linkedItems, $financialLinkAvailable)) {
                continue;
            }

            $reservedPackageQuantity = (int) PackageUsageReservation::query()
                ->where('tenant_id', $tenantId)
                ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
                ->where('customer_package_id', $package->getKey())
                ->where('status', 'reserved')
                ->sum('sessions_reserved');

            $availableSessions = max(0, $package->remaining_sessions - $reservedPackageQuantity);

            if ($availableSessions <= 0) {
                continue;
            }

            $services = [];
            foreach ($package->serviceBalances as $balance) {
                $serviceId = $balance->service_id;

                $reservedServiceQuantity = (int) PackageUsageReservation::query()
                    ->where('tenant_id', $tenantId)
                    ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
                    ->where('customer_package_id', $package->getKey())
                    ->where('service_id', $serviceId)
                    ->where('status', 'reserved')
                    ->sum('sessions_reserved');

                $availableForService = max(0, min($availableSessions, (int) $balance->remaining_quantity - $reservedServiceQuantity));

                if ($availableForService > 0) {
                    $services[] = [
                        'service_id' => $serviceId,
                        'service_name' => $balance->service?->name ?? 'Serviço',
                        'remaining_quantity' => $balance->remaining_quantity,
                        'reserved_quantity' => $reservedServiceQuantity,
                        'available_quantity' => $availableForService,
                    ];
                }
            }

            if (empty($services)) {
                continue;
            }

            $activePackages[] = [
                'id' => $package->getKey(),
                'name' => $package->name_snapshot ?: ($package->packageTemplate?->name ?? 'Pacote'),
                'total_sessions' => $package->total_sessions,
                'remaining_sessions' => $package->remaining_sessions,
                'reserved_sessions' => $reservedPackageQuantity,
                'available_sessions' => $availableSessions,
                'expires_at' => $package->expires_at?->toDateString(),
                'services' => $services,
            ];
        }

        return response()->json([
            'data' => $activePackages,
        ]);
    }
}
