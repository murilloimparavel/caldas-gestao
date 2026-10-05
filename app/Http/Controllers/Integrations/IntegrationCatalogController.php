<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\IntegrationCatalogReadRequest;
use App\Support\Integrations\IntegrationCatalogQuery;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;

final class IntegrationCatalogController extends Controller
{
    public function categories(IntegrationCatalogReadRequest $request, TenantContext $context, IntegrationCatalogQuery $catalog): JsonResponse
    {
        return $this->paginated($catalog->categories($context, $request->perPage(), $request->searchTerm(), $request->page()));
    }

    public function category(TenantContext $context, IntegrationCatalogQuery $catalog, string $category): JsonResponse
    {
        $record = $catalog->category($context, $category);
        abort_if($record === null, 404);

        return $this->record($record);
    }

    public function services(IntegrationCatalogReadRequest $request, TenantContext $context, IntegrationCatalogQuery $catalog): JsonResponse
    {
        return $this->paginated($catalog->services($context, $request->perPage(), $request->searchTerm(), $request->page()));
    }

    public function service(TenantContext $context, IntegrationCatalogQuery $catalog, string $service): JsonResponse
    {
        $record = $catalog->service($context, $service);
        abort_if($record === null, 404);

        return $this->record($record);
    }

    public function professionals(IntegrationCatalogReadRequest $request, TenantContext $context, IntegrationCatalogQuery $catalog): JsonResponse
    {
        return $this->paginated($catalog->professionals($context, $request->perPage(), $request->searchTerm(), $request->page()));
    }

    public function professional(TenantContext $context, IntegrationCatalogQuery $catalog, string $professional): JsonResponse
    {
        $record = $catalog->professional($context, $professional);
        abort_if($record === null, 404);

        return $this->record($record);
    }

    public function setupStatus(TenantContext $context, IntegrationCatalogQuery $catalog): JsonResponse
    {
        return $this->record($catalog->setupStatus($context));
    }

    /** @param array<string, mixed> $record */
    private function record(array $record): JsonResponse
    {
        return response()->json(['data' => $record])->header('Cache-Control', 'no-store, private');
    }

    /** @param Paginator<int, array<string, mixed>> $page */
    private function paginated(Paginator $page): JsonResponse
    {
        return response()->json([
            'data' => array_values($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
