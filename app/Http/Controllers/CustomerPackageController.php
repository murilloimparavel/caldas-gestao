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
use App\Http\Requests\RestoreCustomerPackageRequest;
use App\Http\Requests\ReversePackageUsageRequest;
use App\Http\Requests\SellCustomerPackageRequest;
use App\Models\CustomerPackage;
use App\Models\FinancialObligation;
use App\Models\PackageUsage;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
}
