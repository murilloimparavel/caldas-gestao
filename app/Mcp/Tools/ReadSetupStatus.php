<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\IntegrationMcpContextResolver;
use App\Support\Integrations\IntegrationCatalogQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read boolean and aggregate setup indicators for the authorized tenant unit, including catalog counts, active availability rules, and booking readiness blockers. No names, customers, contacts, notes, or sales are returned.')]
#[IsReadOnly]
#[IsIdempotent]
final class ReadSetupStatus extends Tool
{
    public function handle(Request $request, IntegrationMcpContextResolver $resolver, IntegrationCatalogQuery $catalog): ResponseFactory
    {
        $context = $resolver->resolve()->requireCapability('setup:read');

        return Response::structured([
            'data' => $catalog->setupStatus($context->tenantContext),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
