<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\Packages\CreatePackageTemplate;
use App\Actions\Marketing\Packages\DeactivatePackageTemplate;
use App\Actions\Marketing\Packages\ReactivatePackageTemplate;
use App\Actions\Marketing\Packages\UpdatePackageTemplate;
use App\Http\Requests\PackageTemplateRequest;
use App\Models\FinancialObligation;
use App\Models\PackageTemplate;
use App\Models\Professional;
use App\Models\Service;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PackageTemplateController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', PackageTemplate::class);

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'active');

        $packages = PackageTemplate::query()
            ->with(['services:id,name,price_cents'])
            ->withCount(['customerPackages' => fn ($query) => $query->where('status', '!=', 'archived')])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $serviceOptions = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents'])
            ->map(fn (Service $srv) => [
                'id' => $srv->id,
                'name' => $srv->name,
                'price_cents' => $srv->price_cents,
            ])
            ->values()
            ->all();

        return Inertia::render('packages/index', [
            'packages' => $packages,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'serviceOptions' => $serviceOptions,
        ]);
    }

    public function show(PackageTemplate $packageTemplate, TenantContext $context): Response
    {
        Gate::authorize('view', $packageTemplate);

        $packageTemplate->load(['services:id,name,price_cents,duration_minutes']);

        $serviceOptions = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price_cents'])
            ->map(fn (Service $srv) => [
                'id' => $srv->id,
                'name' => $srv->name,
                'price_cents' => $srv->price_cents,
            ])
            ->values()
            ->all();

        $professionals = Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Professional $professional): array => [
                'id' => $professional->id,
                'name' => $professional->name,
            ])
            ->values()
            ->all();

        $customerPackagesQuery = $packageTemplate->customerPackages()
            ->where('status', '!=', 'archived')
            ->with([
                'customer:id,name,phone,email',
                'serviceBalances.service:id,name',
                'usages.user:id,name',
                'usages.service:id,name',
            ]);

        $canLoadPackageFinancialObligations = Gate::allows('viewAny', FinancialObligation::class)
            && FinancialObligation::hasCustomerPackageLink();

        if ($canLoadPackageFinancialObligations) {
            $customerPackagesQuery->with('financialObligation:id,customer_package_id,amount_cents,status,paid_date,payment_method');
        }

        $customerPackages = $customerPackagesQuery
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('packages/show', [
            'package' => $packageTemplate,
            'serviceOptions' => $serviceOptions,
            'professionals' => $professionals,
            'customerPackages' => $customerPackages,
            'can_view_finance' => Gate::allows('viewAny', FinancialObligation::class),
            'package_finance_available' => $canLoadPackageFinancialObligations,
        ]);
    }

    public function store(PackageTemplateRequest $request, TenantContext $context, CreatePackageTemplate $createPackageTemplate): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createPackageTemplate, $request, $context, $data): array {
            $template = $createPackageTemplate->handle($request->user(), $context, $data);

            return ['resource_id' => $template->getKey(), 'resource_type' => 'package_template'];
        });

        $template = PackageTemplate::query()->findOrFail($reference['resource_id']);

        return to_route('packages.show', $template)->with('success', 'Pacote criado com sucesso.');
    }

    public function update(PackageTemplateRequest $request, TenantContext $context, PackageTemplate $packageTemplate, UpdatePackageTemplate $updatePackageTemplate): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updatePackageTemplate, $request, $context, $packageTemplate, $data): array {
            $updated = $updatePackageTemplate->handle(
                $request->user(),
                $context,
                $packageTemplate,
                $data,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'package_template'];
        });

        $template = PackageTemplate::query()->findOrFail($reference['resource_id']);

        return to_route('packages.show', $template)->with('success', 'Pacote atualizado com sucesso.');
    }

    public function destroy(PackageTemplateRequest $request, TenantContext $context, PackageTemplate $packageTemplate, DeactivatePackageTemplate $deactivatePackageTemplate): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivatePackageTemplate, $request, $context, $packageTemplate, $data): array {
            $deactivated = $deactivatePackageTemplate->handle(
                $request->user(),
                $context,
                $packageTemplate,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'package_template'];
        });

        $template = PackageTemplate::query()->findOrFail($reference['resource_id']);

        return to_route('packages.show', $template)->with('success', 'Pacote desativado.');
    }

    public function reactivate(PackageTemplateRequest $request, TenantContext $context, PackageTemplate $packageTemplate, ReactivatePackageTemplate $reactivatePackageTemplate): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivatePackageTemplate, $request, $context, $packageTemplate, $data): array {
            $reactivated = $reactivatePackageTemplate->handle(
                $request->user(),
                $context,
                $packageTemplate,
                isset($data['lock_version']) ? (int) $data['lock_version'] : null
            );

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'package_template'];
        });

        $template = PackageTemplate::query()->findOrFail($reference['resource_id']);

        return to_route('packages.show', $template)->with('success', 'Pacote reativado.');
    }
}
