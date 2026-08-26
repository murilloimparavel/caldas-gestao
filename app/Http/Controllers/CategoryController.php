<?php

namespace App\Http\Controllers;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeactivateCategory;
use App\Actions\Categories\ReactivateCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Category::class);
        $search = trim((string) $request->string('search'));
        $type = trim((string) $request->string('type'));
        $status = (string) $request->string('status', 'active');

        $categories = Category::query()
            ->withCount(['services', 'products'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ],
        ]);
    }

    public function show(Category $category): Response
    {
        Gate::authorize('view', $category);

        return Inertia::render('categories/show', [
            'category' => $category->load(['services:id,category_id,name,price_cents,duration_minutes,status', 'products:id,category_id,name,sale_price_cents,current_stock,is_active']),
        ]);
    }

    public function store(CategoryRequest $request, TenantContext $context, CreateCategory $createCategory): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createCategory, $request, $context, $data): array {
            $category = $createCategory->handle($request->user(), $context, $data);

            return ['resource_id' => $category->getKey(), 'resource_type' => 'category'];
        });
        $category = Category::query()->findOrFail($reference['resource_id']);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
                'category' => $category,
            ], 201);
        }

        return to_route('categories.show', $category)->with('success', 'Categoria cadastrada com sucesso.');
    }

    public function update(CategoryRequest $request, TenantContext $context, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateCategory, $request, $context, $category, $data): array {
            $updated = $updateCategory->handle($request->user(), $context, $category, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'category'];
        });
        $category = Category::query()->findOrFail($reference['resource_id']);

        return to_route('categories.show', $category)->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(CategoryRequest $request, TenantContext $context, Category $category, DeactivateCategory $deactivateCategory): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateCategory, $request, $context, $category, $data): array {
            $deactivated = $deactivateCategory->handle($request->user(), $context, $category, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'category'];
        });

        return to_route('categories.index')->with('success', 'Categoria inativada com sucesso.');
    }

    public function reactivate(CategoryRequest $request, TenantContext $context, Category $category, ReactivateCategory $reactivateCategory): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($reactivateCategory, $request, $context, $category, $data): array {
            $reactivated = $reactivateCategory->handle($request->user(), $context, $category, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $reactivated->getKey(), 'resource_type' => 'category'];
        });

        return to_route('categories.show', $category)->with('success', 'Categoria reativada com sucesso.');
    }
}
