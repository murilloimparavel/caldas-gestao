<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\IntegrationMcpContextResolver;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read the approved integration capabilities and operation schemas. This returns metadata only and never reads catalog records.')]
#[IsReadOnly]
#[IsIdempotent]
final class ListIntegrationCapabilities extends Tool
{
    public function handle(Request $request, IntegrationMcpContextResolver $resolver, IntegrationCapabilityCatalog $catalog): ResponseFactory
    {
        $context = $resolver->resolve();

        return Response::structured([
            'data' => [
                'capabilities' => $catalog->capabilities(),
                'operations' => $catalog->operations(),
                'enabled_capabilities' => $context->credential->capabilities,
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
