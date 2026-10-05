<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListCatalogCategories;
use App\Mcp\Tools\ListCatalogProfessionals;
use App\Mcp\Tools\ListCatalogServices;
use App\Mcp\Tools\ListIntegrationCapabilities;
use App\Mcp\Tools\ProposeIntegrationOperation;
use App\Mcp\Tools\ReadIntegrationContext;
use App\Mcp\Tools\ReadSetupStatus;
use App\Mcp\Tools\ShowIntegrationOperation;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Caldas Indica Integration MCP')]
#[Version('1.0.0')]
#[Instructions('Use these tools only for an authenticated administrative integration. Read tools expose boolean context, approved capability metadata, authorized category and professional names, service names with price and duration, and aggregate setup indicators scoped to the bound tenant unit. Customers, contacts, notes, sales, and other private catalog fields are excluded. Write tools create proposals only; an administrator must review and confirm them in the Caldas Indica application.')]
final class IntegrationServer extends Server
{
    /** @var array<int|string, Tool|class-string<Tool>|array<int, Tool|class-string<Tool>>> */
    protected array $tools = [
        ReadIntegrationContext::class,
        ListIntegrationCapabilities::class,
        ListCatalogCategories::class,
        ListCatalogServices::class,
        ListCatalogProfessionals::class,
        ReadSetupStatus::class,
        ProposeIntegrationOperation::class,
        ShowIntegrationOperation::class,
    ];

    /** @var array<string, array<string, bool>> */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];
}
