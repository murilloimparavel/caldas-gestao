<?php

use App\Mcp\IntegrationMcpContext;
use App\Mcp\IntegrationMcpContextResolver;
use App\Mcp\ProposedOperationRepository;
use App\Mcp\Servers\IntegrationServer;
use App\Mcp\Tools\ListCatalogCategories;
use App\Mcp\Tools\ListCatalogProfessionals;
use App\Mcp\Tools\ListCatalogServices;
use App\Mcp\Tools\ListIntegrationCapabilities;
use App\Mcp\Tools\ProposeIntegrationOperation;
use App\Mcp\Tools\ReadIntegrationContext;
use App\Mcp\Tools\ReadSetupStatus;
use App\Mcp\Tools\ShowIntegrationOperation;
use App\Models\AvailabilityRule;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Membership;
use App\Models\OnlineBookingGalleryImage;
use App\Models\OnlineBookingSetting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Support\Str;

afterEach(function (): void {
    app()->forgetInstance(IntegrationMcpContextResolver::class);
    app()->forgetInstance(ProposedOperationRepository::class);
    app()->forgetInstance(ProposedOperationService::class);
});

/** @return array{0: IntegrationMcpContext, 1: string} */
function mcpTestContext(): array
{
    $user = User::factory()->make();
    $tenant = Tenant::factory()->make(['id' => (string) Str::uuid()]);
    $unit = Unit::factory()->make([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->getKey(),
    ]);
    $membership = Membership::factory()->make([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
    ]);
    $credentialId = (string) Str::uuid();
    $credential = new IntegrationCredential([
        'id' => $credentialId,
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => ['context:read', 'operations:propose', 'catalog:read', 'setup:read'],
        'expires_at' => now()->addDay(),
    ]);

    return [
        new IntegrationMcpContext(
            $user,
            new TenantContext($user, $tenant, $membership, $unit),
            $credential,
        ),
        $credentialId,
    ];
}

it('registers only the approved MCP tools', function (): void {
    IntegrationServer::tools()
        ->assertRegistered([
            ReadIntegrationContext::class,
            ListIntegrationCapabilities::class,
            ListCatalogCategories::class,
            ListCatalogServices::class,
            ListCatalogProfessionals::class,
            ReadSetupStatus::class,
            ProposeIntegrationOperation::class,
            ShowIntegrationOperation::class,
        ]);
});

it('returns only boolean context and granted capability names', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    IntegrationServer::tool(ReadIntegrationContext::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.actor.authenticated', true)
            ->where('data.tenant.bound', true)
            ->where('data.unit.bound', true)
            ->where('data.capabilities', ['context:read', 'operations:propose', 'catalog:read', 'setup:read'])
            ->missing('data.tenant.id')
            ->missing('data.unit.id'));
});

it('exposes runtime capability schemas without exposing catalog records', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    IntegrationServer::tool(ListIntegrationCapabilities::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.capabilities.0.id', 'context:read')
            ->where('data.capabilities.1.id', 'operations:propose')
            ->where('data.capabilities.2.id', 'catalog:read')
            ->where('data.capabilities.3.id', 'setup:read')
            ->where('data.operations.0.operation', 'category.create')
            ->where('data.operations.4.operation', 'service.create')
            ->where('data.enabled_capabilities', ['context:read', 'operations:propose', 'catalog:read', 'setup:read'])
            ->missing('data.records')
            ->missing('data.services'))
        ->assertDontSee(['Corte clássico', '4500']);
});

it('uses every capability catalog operation in the proposal tool schema', function (): void {
    $catalogOperations = array_column(app(IntegrationCapabilityCatalog::class)->operations(), 'operation');
    $tool = app(ProposeIntegrationOperation::class)->toArray();

    expect($tool['inputSchema']['properties']['operation']['enum'])->toBe($catalogOperations)
        ->toHaveCount(7);
});

it('creates a proposal and returns metadata without submitted values', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    $proposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'service.create',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => [
            'name' => 'Corte clássico',
            'duration_minutes' => 30,
            'price_cents' => 4500,
        ],
    ]);
    $proposal->exists = true;
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldReceive('propose')
        ->once()
        ->withArgs(function (IntegrationCredential $credential, TenantContext $tenantContext, string $operation, array $input, string $key) use ($context): bool {
            return $credential->is($context->credential)
                && $tenantContext === $context->tenantContext
                && $operation === 'service.create'
                && $input['name'] === 'Corte clássico'
                && $key === 'service-create-1';
        })
        ->andReturn($proposal);
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'service.create',
        'input' => [
            'name' => 'Corte clássico',
            'duration_minutes' => 30,
            'price_cents' => 4500,
        ],
        'idempotency_key' => 'service-create-1',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.operation', 'service.create')
            ->where('data.status', ProposedOperation::STATUS_PENDING_CONFIRMATION)
            ->where('data.confirmation_required', true)
            ->where('data.changed_fields', ['name', 'duration_minutes', 'price_cents'])
            ->missing('data.input')
            ->missing('data.summary'))
        ->assertDontSee(['Corte clássico', '4500']);
});

it('returns no professional target or relationship identifiers in MCP update metadata', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);
    $professionalId = (string) Str::uuid();
    $serviceId = (string) Str::uuid();
    $proposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'professional.update',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => [
            'professional_id' => $professionalId,
            'expected_version' => 4,
            'name' => 'Barbeiro atual',
            'status' => 'inactive',
            'service_ids' => [$serviceId],
            '_provided_fields' => ['status'],
            '_snapshot' => ['service_ids' => [$serviceId]],
        ],
    ]);
    $proposal->exists = true;
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldReceive('propose')->once()->andReturn($proposal);
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'professional.update',
        'input' => ['professional_name' => 'Barbeiro atual', 'status' => 'inactive'],
        'idempotency_key' => 'professional-update-mcp',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.operation', 'professional.update')
            ->where('data.changed_fields', ['status'])
            ->missing('data.professional_id')
            ->missing('data.expected_version')
            ->missing('data.service_ids'))
        ->assertDontSee([$professionalId, $serviceId]);
});

it('rejects availability and booking proposal operations without invoking the proposal service', function (): void {
    [$owner, $tenant, $unit, , $professional] = onlineBookingWorkspace();
    $membership = Membership::query()->where('tenant_id', $tenant->getKey())->where('user_id', $owner->getKey())->firstOrFail();
    $tenantContext = new TenantContext($owner, $tenant, $membership, $unit);
    $credential = new IntegrationCredential([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => ['operations:propose'],
        'expires_at' => now()->addDay(),
    ]);
    $context = new IntegrationMcpContext($owner, $tenantContext, $credential);
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->twice()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    $availabilityInput = [
        'professional_id' => $professional->getKey(),
        'weekday' => 1,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'timezone' => 'America/Sao_Paulo',
    ];
    $bookingInput = ['expected_version' => $unit->lock_version, 'online_booking_enabled' => true];
    $availabilityProposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'availability_rule.create',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => $availabilityInput,
    ]);
    $bookingProposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'booking.settings.update',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => $bookingInput,
    ]);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'availability_rule.create',
        'input' => $availabilityInput,
        'idempotency_key' => 'availability-rule-create-1',
    ])
        ->assertHasErrors();

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'booking.settings.update',
        'input' => $bookingInput,
        'idempotency_key' => 'booking-settings-update-1',
    ])
        ->assertHasErrors();

    expect($unit->fresh()->lock_version)->toBe($bookingInput['expected_version'])
        ->and($unit->fresh()->online_booking_enabled)->toBeFalse()
        ->and(AvailabilityRule::query()->where('professional_id', $professional->getKey())->exists())->toBeFalse();
});

it('rejects availability proposals with a professional outside the credential tenant', function (): void {
    [$context] = mcpTestContext();
    $input = [
        'professional_id' => (string) Str::uuid(),
        'weekday' => 1,
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'timezone' => 'America/Sao_Paulo',
    ];
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'availability_rule.create',
        'input' => $input,
        'idempotency_key' => 'availability-rule-cross-tenant',
    ])
        ->assertHasErrors(['operation']);
});

it('rejects unsafe booking draft media references and cross-tenant catalog IDs before proposing', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->times(4)->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    $unsafeDrafts = [
        ['identity' => ['cover_image_path' => 'tenants/foreign/cover.webp']],
        ['gallery' => [['path' => 'tenants/foreign/gallery.webp', 'thumbnail_path' => 'tenants/foreign/thumb.webp']]],
        ['seo' => ['title' => 'Agenda', 'open_graph' => ['image_url' => 'https://media.example.test/foreign.webp']]],
    ];

    foreach ($unsafeDrafts as $index => $content) {
        IntegrationServer::tool(ProposeIntegrationOperation::class, [
            'operation' => 'booking.draft.update',
            'input' => ['expected_revision' => 0, 'content' => $content],
            'idempotency_key' => 'unsafe-booking-draft-'.$index,
        ])->assertHasErrors();
    }

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'booking.draft.update',
        'input' => [
            'expected_revision' => 0,
            'content' => ['service_ids' => [(string) Str::uuid()]],
        ],
        'idempotency_key' => 'cross-tenant-booking-draft-service',
    ])->assertHasErrors();
});

it('rejects a gallery image owned by another tenant before creating a booking draft proposal', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $foreignTenant = Tenant::factory()->create();
    $foreignUnit = Unit::factory()->create(['tenant_id' => $foreignTenant->getKey()]);
    $foreignImage = OnlineBookingGalleryImage::query()->create([
        'tenant_id' => $foreignTenant->getKey(),
        'unit_id' => $foreignUnit->getKey(),
        'path' => 'online-booking/'.$foreignUnit->getKey().'/foreign.webp',
        'thumbnail_path' => 'online-booking/'.$foreignUnit->getKey().'/foreign-thumb.webp',
        'alt_text' => 'Imagem externa',
    ]);
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    $credential = new IntegrationCredential([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => ['operations:propose'],
        'expires_at' => now()->addDay(),
    ]);
    $context = new IntegrationMcpContext(
        $owner,
        new TenantContext($owner, $tenant, $membership, $unit),
        $credential,
    );
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'booking.draft.update',
        'input' => [
            'expected_revision' => 0,
            'content' => ['gallery' => [[
                'path' => $foreignImage->path,
                'thumbnail_path' => $foreignImage->thumbnail_path,
                'alt_text' => $foreignImage->alt_text,
            ]]],
        ],
        'idempotency_key' => 'foreign-tenant-gallery-image',
    ])->assertHasErrors();
});

it('rejects booking draft operations even when media belongs to the credential unit', function (): void {
    [$owner, $tenant, $unit] = onlineBookingWorkspace();
    $membership = Membership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $owner->getKey())
        ->firstOrFail();
    $coverPath = 'online-booking/'.$unit->getKey().'/cover/owned.webp';
    OnlineBookingSetting::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'public_slug' => $unit->slug,
        'cover_image_path' => $coverPath,
    ]);
    $galleryImage = OnlineBookingGalleryImage::query()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'path' => 'online-booking/'.$unit->getKey().'/gallery/owned.webp',
        'thumbnail_path' => 'online-booking/'.$unit->getKey().'/gallery/owned-thumb.webp',
        'alt_text' => 'Cadeira da barbearia',
    ]);
    $credential = new IntegrationCredential([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'capabilities' => ['operations:propose'],
        'expires_at' => now()->addDay(),
    ]);
    $context = new IntegrationMcpContext(
        $owner,
        new TenantContext($owner, $tenant, $membership, $unit),
        $credential,
    );
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    $input = [
        'expected_revision' => 0,
        'content' => [
            'identity' => ['cover_image_path' => $coverPath],
            'gallery' => [[
                'path' => $galleryImage->path,
                'thumbnail_path' => $galleryImage->thumbnail_path,
                'alt_text' => $galleryImage->alt_text,
            ]],
        ],
    ];
    $proposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'booking.draft.update',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => $input,
    ]);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'booking.draft.update',
        'input' => $input,
        'idempotency_key' => 'unit-owned-media',
    ])->assertHasErrors();
});

it('rejects booking draft configuration fields over MCP', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    $input = [
        'expected_revision' => 0,
        'content' => [
            'schema_version' => 1,
            'theme' => ['brand_color' => '#123456', 'accent_color' => '#abcdef'],
            'seo' => ['title' => 'Barbearia', 'description' => 'Agende seu horário.'],
            'identity' => ['description' => 'Atendimento com hora marcada', 'phone' => '11999999999'],
            'sections' => [['key' => 'gallery', 'enabled' => false]],
            'public_hours' => ['1' => ['enabled' => true, 'starts_at' => '09:00', 'ends_at' => '18:00']],
            'booking_policy' => ['booking_flow' => 'service_first', 'minimum_notice_minutes' => 60],
            'appearance' => [
                'brand_name' => 'Barbearia', 'headline' => 'Agende', 'subheadline' => 'Escolha seu horário',
                'primary_color' => '#123456', 'background_color' => '#ffffff', 'cta_label' => 'Continuar',
            ],
        ],
    ];
    $proposal = new ProposedOperation([
        'id' => (string) Str::uuid(),
        'operation_key' => 'booking.draft.update',
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(10),
        'input' => $input,
    ]);
    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldNotReceive('propose');
    app()->instance(ProposedOperationService::class, $operations);

    IntegrationServer::tool(ProposeIntegrationOperation::class, [
        'operation' => 'booking.draft.update',
        'input' => $input,
        'idempotency_key' => 'safe-booking-draft',
    ])->assertHasErrors();
});

it('returns proposal status without returning a payload snapshot', function (): void {
    [$context] = mcpTestContext();
    $resolver = Mockery::mock(IntegrationMcpContextResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($context);
    app()->instance(IntegrationMcpContextResolver::class, $resolver);

    $proposalId = (string) Str::uuid();
    $proposal = new ProposedOperation([
        'id' => $proposalId,
        'operation_key' => 'unit.update',
        'status' => ProposedOperation::STATUS_SUCCEEDED,
        'expires_at' => now()->addMinutes(10),
        'input' => ['name' => 'Private unit name', 'price_cents' => 9000],
    ]);
    $proposal->exists = true;

    $operations = Mockery::mock(ProposedOperationService::class);
    $operations->shouldReceive('show')->once()->with($proposal, $context->user, $context->tenantContext)->andReturn($proposal);
    app()->instance(ProposedOperationService::class, $operations);

    $repository = Mockery::mock(ProposedOperationRepository::class);
    $repository->shouldReceive('findOrFail')->once()->with($proposalId)->andReturn($proposal);
    app()->instance(ProposedOperationRepository::class, $repository);

    IntegrationServer::tool(ShowIntegrationOperation::class, ['proposal_id' => $proposalId])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.id', $proposalId)
            ->where('data.operation', 'unit.update')
            ->where('data.status', ProposedOperation::STATUS_SUCCEEDED)
            ->missing('data.input')
            ->missing('data.summary'))
        ->assertDontSee(['Private unit name', '9000']);
});
