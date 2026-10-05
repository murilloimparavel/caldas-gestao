<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\CatalogPagination;
use App\Mcp\IntegrationMcpContextResolver;
use App\Support\Integrations\IntegrationCatalogQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List a page of service names, duration in minutes, and price in cents for the authorized tenant unit. Check pagination.has_more to continue; no IDs, counts, status, category links, descriptions, images, customers, sales, or contacts are returned.')]
#[IsReadOnly]
#[IsIdempotent]
final class ListCatalogServices extends Tool
{
    public function handle(Request $request, IntegrationMcpContextResolver $resolver, IntegrationCatalogQuery $catalog): ResponseFactory
    {
        $context = $resolver->resolve()->requireCapability('catalog:read');
        $search = $this->search($request->get('search'));
        $perPage = $this->perPage($request->get('per_page'));
        $page = $this->page($request->get('page'));

        return Response::structured([
            'data' => CatalogPagination::from($catalog->services($context->tenantContext, $perPage, $search, $page)),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->max(160)->description('Optional service name filter.'),
            'per_page' => $schema->integer()->min(1)->max(100)->default(50)->description('Maximum records to return.'),
            'page' => $schema->integer()->min(1)->max(10000)->default(1)->description('One-based page number, capped at 10000.'),
        ];
    }

    private function search(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > 160) {
            throw ValidationException::withMessages(['search' => ['The search filter must be a string of at most 160 characters.']]);
        }

        return trim($value) === '' ? null : trim($value);
    }

    private function perPage(mixed $value): int
    {
        if ($value === null) {
            return 50;
        }

        if (! is_int($value) || $value < 1 || $value > 100) {
            throw ValidationException::withMessages(['per_page' => ['The per_page value must be an integer between 1 and 100.']]);
        }

        return $value;
    }

    private function page(mixed $value): int
    {
        if ($value === null) {
            return 1;
        }

        if (! is_int($value) || $value < 1 || $value > 10000) {
            throw ValidationException::withMessages(['page' => ['The page value must be an integer between 1 and 10000.']]);
        }

        return $value;
    }
}
