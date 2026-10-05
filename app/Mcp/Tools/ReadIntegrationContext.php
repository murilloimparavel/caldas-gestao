<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\IntegrationMcpContextResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read only whether the authenticated integration is bound to an administrative account, tenant, and unit. No identifiers or private data are returned.')]
#[IsReadOnly]
#[IsIdempotent]
final class ReadIntegrationContext extends Tool
{
    public function handle(Request $request, IntegrationMcpContextResolver $resolver): ResponseFactory
    {
        $context = $resolver->resolve()->requireCapability('context:read');

        return Response::structured([
            'data' => [
                'actor' => ['authenticated' => true],
                'tenant' => ['bound' => true],
                'unit' => ['bound' => $context->tenantContext->unit !== null],
                'capabilities' => $context->credential->capabilities,
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
