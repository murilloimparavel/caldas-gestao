<?php

namespace App\Http\Controllers;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeactivateProduct;
use App\Actions\Products\UpdateProduct;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\OperationalMutation;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function __construct(private readonly OperationalMutation $mutation) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Product::class);
        $search = trim((string) $request->string('search'));
        $categoryId = trim((string) $request->string('category_id'));

        $products = Product::query()
            ->with('category:id,name')
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categoryOptions = Category::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->where('is_active', true)
            ->whereIn('type', ['product', 'general'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $cat) => ['id' => $cat->id, 'name' => $cat->name])
            ->values()
            ->all();

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
            ],
            'categoryOptions' => $categoryOptions,
        ]);
    }

    public function show(Product $product, TenantContext $context): Response
    {
        Gate::authorize('view', $product);

        $categoryOptions = Category::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereIn('type', ['product', 'general'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $cat) => ['id' => $cat->id, 'name' => $cat->name])
            ->values()
            ->all();

        $movements = $product->inventoryMovements()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/show', [
            'product' => $product->load('category:id,name'),
            'categoryOptions' => $categoryOptions,
            'movements' => $movements,
        ]);
    }

    public function store(ProductRequest $request, TenantContext $context, CreateProduct $createProduct): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($createProduct, $request, $context, $data): array {
            $product = $createProduct->handle($request->user(), $context, $data);

            return ['resource_id' => $product->getKey(), 'resource_type' => 'product'];
        });
        $product = Product::query()->findOrFail($reference['resource_id']);

        return to_route('products.show', $product)->with('success', 'Produto cadastrado com sucesso.');
    }

    public function update(ProductRequest $request, TenantContext $context, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        $data = $request->validated();
        $reference = $this->mutation->execute($request, $context, $request->user(), $data, function () use ($updateProduct, $request, $context, $product, $data): array {
            $updated = $updateProduct->handle($request->user(), $context, $product, $data);

            return ['resource_id' => $updated->getKey(), 'resource_type' => 'product'];
        });
        $product = Product::query()->findOrFail($reference['resource_id']);

        return to_route('products.show', $product)->with('success', 'Produto atualizado com sucesso.');
    }

    public function destroy(ProductRequest $request, TenantContext $context, Product $product, DeactivateProduct $deactivateProduct): RedirectResponse
    {
        $data = $request->validated();
        $this->mutation->execute($request, $context, $request->user(), $data, function () use ($deactivateProduct, $request, $context, $product, $data): array {
            $deactivated = $deactivateProduct->handle($request->user(), $context, $product, isset($data['lock_version']) ? (int) $data['lock_version'] : null);

            return ['resource_id' => $deactivated->getKey(), 'resource_type' => 'product'];
        });

        return to_route('products.index')->with('success', 'Produto inativado com sucesso.');
    }
}
