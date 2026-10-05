<?php

use App\Actions\Identity\OnboardTenant;
use App\Mcp\IntegrationMcpContext;
use App\Mcp\IntegrationMcpContextResolver;
use App\Mcp\Servers\IntegrationServer;
use App\Mcp\Tools\ListCatalogCategories;
use App\Mcp\Tools\ListCatalogProfessionals;
use App\Mcp\Tools\ListCatalogServices;
use App\Mcp\Tools\ReadSetupStatus;
use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Membership;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

function mcpCatalogReadContext(): IntegrationMcpContext
{
    $user = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($user, [
        'name' => 'MCP catalog '.Str::random(8),
        'slug' => 'mcp-catalog-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $user->getKey())
        ->firstOrFail();
    $credential = new IntegrationCredential([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => ['catalog:read', 'setup:read'],
        'expires_at' => now()->addDay(),
    ]);

    return new IntegrationMcpContext(
        $user,
        new TenantContext($user, $tenant, $membership, $unit),
        $credential,
    );
}

function bindMcpCatalogResolver(IntegrationMcpContext $context): void
{
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);
}

it('registers the approved catalog and setup read tools', function (): void {
    IntegrationServer::tools()->assertRegistered([
        ListCatalogCategories::class,
        ListCatalogServices::class,
        ListCatalogProfessionals::class,
        ReadSetupStatus::class,
    ]);
});

it('lists category names scoped to the credential tenant and unit', function (): void {
    $context = mcpCatalogReadContext();
    $category = Category::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $context->tenantContext->unit?->getKey(),
        'name' => 'Cortes',
        'type' => 'service',
    ]);
    Category::factory()->create([
        'name' => 'Fora do filtro',
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $context->tenantContext->unit?->getKey(),
    ]);
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ListCatalogCategories::class, [
        'search' => 'Cortes',
        'per_page' => 1,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.items.0.name', 'Cortes')
            ->where('data.pagination.current_page', 1)
            ->where('data.pagination.per_page', 1)
            ->where('data.pagination.has_more', false)
            ->missing('data.pagination.total')
            ->missing('data.pagination.last_page')
            ->missing('data.items.0.id')
            ->missing('data.items.0.type')
            ->missing('data.items.0.is_active')
            ->missing('data.items.0.description')
            ->missing('data.items.0.tenant_id'));

    IntegrationServer::tool(ListCatalogCategories::class, ['per_page' => 1, 'page' => 1])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.items.0.name', 'Cortes')
            ->where('data.pagination.current_page', 1)
            ->where('data.pagination.per_page', 1)
            ->where('data.pagination.has_more', true));
    IntegrationServer::tool(ListCatalogCategories::class, ['per_page' => 1, 'page' => 2])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.items.0.name', 'Fora do filtro')
            ->where('data.pagination.current_page', 2)
            ->where('data.pagination.has_more', false));
    IntegrationServer::tool(ListCatalogCategories::class, ['page' => 10001])->assertHasErrors();
});

it('lists service price and duration without private fields', function (): void {
    $context = mcpCatalogReadContext();
    $category = Category::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $context->tenantContext->unit?->getKey(),
        'type' => 'service',
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $context->tenantContext->unit?->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Corte premium',
        'duration_minutes' => 45,
        'price_cents' => 7500,
    ]);
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ListCatalogServices::class, ['search' => 'premium'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.items.0.name', 'Corte premium')
            ->where('data.items.0.duration_minutes', 45)
            ->where('data.items.0.price_cents', 7500)
            ->missing('data.items.0.id')
            ->missing('data.items.0.category_id')
            ->missing('data.items.0.status')
            ->missing('data.items.0.description')
            ->missing('data.items.0.image_path')
            ->missing('data.items.0.tenant_id'));
});

it('lists professional names without contact fields', function (): void {
    $context = mcpCatalogReadContext();
    $professional = Professional::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $context->tenantContext->unit?->getKey(),
        'name' => 'Barbeiro autorizado',
        'email' => 'private@example.test',
        'phone' => '+55 11 99999-0000',
    ]);
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ListCatalogProfessionals::class, ['search' => 'autorizado'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.items.0.name', 'Barbeiro autorizado')
            ->missing('data.items.0.id')
            ->missing('data.items.0.status')
            ->missing('data.items.0.email')
            ->missing('data.items.0.phone')
            ->missing('data.items.0.avatar_url'))
        ->assertDontSee(['private@example.test', '+55 11 99999-0000']);
});

it('returns aggregate setup indicators only', function (): void {
    $context = mcpCatalogReadContext();
    $unit = $context->tenantContext->unit;
    expect($unit)->toBeInstanceOf(Unit::class);
    $unit->update([
        'timezone' => 'America/Sao_Paulo',
        'online_booking_enabled' => true,
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'status' => 'active',
    ]);
    $professional->services()->attach($service, [
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    AvailabilityRule::factory()->create([
        'tenant_id' => $context->tenantContext->tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'status' => 'active',
    ]);
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ReadSetupStatus::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.unit.active', true)
            ->where('data.unit.timezone_configured', true)
            ->where('data.unit.online_booking_enabled', true)
            ->where('data.catalog.active_services', 1)
            ->where('data.catalog.active_professionals', 1)
            ->where('data.catalog.active_service_professional_pair', true)
            ->where('data.availability.active_rules', 1)
            ->where('data.booking.site_exists', false)
            ->where('data.booking.draft_exists', false)
            ->missing('data.unit.name')
            ->missing('data.booking.customer'));
});

it('requires the explicit catalog capability for catalog reads', function (): void {
    $context = mcpCatalogReadContext();
    $context = new IntegrationMcpContext(
        $context->user,
        $context->tenantContext,
        new IntegrationCredential([
            'id' => (string) Str::uuid(),
            'capabilities' => ['setup:read'],
            'expires_at' => now()->addDay(),
        ]),
    );
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ListCatalogServices::class)
        ->assertHasErrors(['catalog:read']);
});

it('requires the explicit setup capability for setup reads', function (): void {
    $context = mcpCatalogReadContext();
    $context = new IntegrationMcpContext(
        $context->user,
        $context->tenantContext,
        new IntegrationCredential([
            'id' => (string) Str::uuid(),
            'capabilities' => ['catalog:read'],
            'expires_at' => now()->addDay(),
        ]),
    );
    bindMcpCatalogResolver($context);

    IntegrationServer::tool(ReadSetupStatus::class)
        ->assertHasErrors(['setup:read']);
});
