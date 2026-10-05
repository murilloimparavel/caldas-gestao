<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectorOptionsRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\SelectorOptionsService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class SelectorOptionsController extends Controller
{
    public function __invoke(
        SelectorOptionsRequest $request,
        TenantContext $context,
        SelectorOptionsService $selectorOptions,
    ): JsonResponse {
        $filters = $request->validated();

        if ($filters['resource'] === 'inventory-products') {
            abort_unless(
                Gate::allows('create', InventoryMovement::class)
                    || Gate::allows('create', Product::class),
                403,
            );
        } else {
            Gate::authorize('viewAny', $selectorOptions->modelFor($filters['resource']));
        }

        $paginator = $selectorOptions->paginate($context, $filters);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Model $option): array => $selectorOptions->optionFor($filters['resource'], $option))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
