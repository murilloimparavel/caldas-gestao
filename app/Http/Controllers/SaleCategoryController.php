<?php

namespace App\Http\Controllers;

use App\Actions\SaleCategories\CreateSaleCategory;
use App\Actions\SaleCategories\DeactivateSaleCategory;
use App\Actions\SaleCategories\ReactivateSaleCategory;
use App\Actions\SaleCategories\UpdateSaleCategory;
use App\Http\Requests\SaleCategoryRequest;
use App\Models\SaleCategory;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SaleCategoryController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', SaleCategory::class);
        $search = trim((string) $request->string('search'));
        $type = trim((string) $request->string('type'));
        $uniquenessScope = trim((string) $request->string('uniqueness_scope'));
        $status = (string) $request->string('status', 'active');

        $categories = SaleCategory::query()
            ->withCount(['sales'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('key', 'like', "%{$search}%")))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($uniquenessScope !== '', fn ($query) => $query->where('uniqueness_scope', $uniquenessScope))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('sale-categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'uniqueness_scope' => $uniquenessScope,
                'status' => $status,
            ],
        ]);
    }

    public function show(SaleCategory $saleCategory, TenantContext $context): Response
    {
        Gate::authorize('view', $saleCategory);

        $unit = $context->unit;
        $defaultCategoryId = $unit?->appointment_default_sale_category_id;
        $automation = [
            'enabled' => (bool) ($unit->appointment_sales_automation_enabled ?? false),
            'appointment_automation_enabled' => (bool) ($unit->appointment_sales_automation_enabled ?? false),
            'default_sale_category_id' => $defaultCategoryId,
            'default_appointment_category_id' => $defaultCategoryId,
        ];
        $category = $saleCategory->loadCount('sales');
        $category->setAttribute('is_default_for_appointments', $defaultCategoryId === $saleCategory->getKey());
        $category->setAttribute('appointment_automation_enabled', $automation['enabled']);

        return Inertia::render('sale-categories/show', [
            'category' => $category,
            'appointment_automation' => $automation,
        ]);
    }

    public function store(SaleCategoryRequest $request, TenantContext $context, CreateSaleCategory $createSaleCategory): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createSaleCategory, $request, $context, $data): array {
            $category = $createSaleCategory->handle($request->user(), $context, $data);

            return ['resource_id' => $category->getKey(), 'resource_type' => 'sale-category'];
        });
        $category = SaleCategory::query()->findOrFail($reference['resource_id']);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
                'category' => $category,
            ], 201);
        }

        return to_route('sale-categories.show', $category)->with('success', 'Categoria de comanda cadastrada com sucesso.');
    }

    public function update(SaleCategoryRequest $request, TenantContext $context, SaleCategory $saleCategory, UpdateSaleCategory $updateSaleCategory): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateSaleCategory, $request, $context, $saleCategory, $data): array {
            $updated = $updateSaleCategory->handle($request->user(), $context, $saleCategory, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'sale-category'];
        });
        $category = SaleCategory::query()->findOrFail($reference['resource_id']);

        return to_route('sale-categories.show', $category)->with('success', 'Categoria de comanda atualizada com sucesso.');
    }

    public function destroy(SaleCategoryRequest $request, TenantContext $context, SaleCategory $saleCategory, DeactivateSaleCategory $deactivateSaleCategory): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateSaleCategory, $request, $context, $saleCategory, $data): array {
            $deactivated = $deactivateSaleCategory->handle($request->user(), $context, $saleCategory, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'sale-category'];
        });

        return to_route('sale-categories.index')->with('success', 'Categoria de comanda inativada com sucesso.');
    }

    public function reactivate(SaleCategoryRequest $request, TenantContext $context, SaleCategory $saleCategory, ReactivateSaleCategory $reactivateSaleCategory): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivateSaleCategory, $request, $context, $saleCategory, $data): array {
            $reactivated = $reactivateSaleCategory->handle($request->user(), $context, $saleCategory, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'sale-category'];
        });

        return to_route('sale-categories.show', $saleCategory)->with('success', 'Categoria de comanda reativada com sucesso.');
    }
}
