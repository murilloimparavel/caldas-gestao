<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Requests\Integrations\ProposeServiceOperationRequest;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Symfony\Component\Yaml\Yaml;

/** @return array<string, array{methods: list<string>, middleware: list<string>}> */
function integrationRouteContract(): array
{
    $routes = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');

        if (! str_starts_with($uri, '/api/v1/')) {
            continue;
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
        sort($methods);

        $routes[$uri] = [
            'methods' => $methods,
            'middleware' => $route->gatherMiddleware(),
        ];
    }

    ksort($routes);

    return $routes;
}

/** @return array<string, mixed> */
function integrationOpenApiDocument(): array
{
    $document = Yaml::parseFile(base_path('docs/openapi/integrations-v1.yaml'));

    expect($document)->toBeArray();

    return $document;
}

it('keeps the documented integration paths and methods aligned with Laravel routes', function () {
    $document = integrationOpenApiDocument();
    $documentedRoutes = [];
    $operationIds = [];

    foreach ($document['paths'] as $path => $pathItem) {
        foreach ($pathItem as $method => $operation) {
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $uri = '/'.ltrim($path, '/');
            $documentedRoutes[$uri][] = strtoupper($method);
            $operationIds[] = $operation['operationId'] ?? null;
        }
    }

    foreach ($documentedRoutes as &$methods) {
        sort($methods);
    }
    unset($methods);
    ksort($documentedRoutes);

    $runtimeRoutes = integrationRouteContract();
    $runtimeMethods = array_map(fn (array $route): array => $route['methods'], $runtimeRoutes);

    expect($documentedRoutes)->toBe($runtimeMethods)
        ->and($operationIds)->not->toContain(null)
        ->and(array_unique($operationIds))->toHaveCount(count($operationIds));
});

it('documents the capability required by each integration route', function () {
    $document = integrationOpenApiDocument();
    $runtimeRoutes = integrationRouteContract();

    foreach ($document['paths'] as $path => $pathItem) {
        foreach ($pathItem as $method => $operation) {
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $uri = '/'.ltrim($path, '/');
            $routeMiddleware = $runtimeRoutes[$uri]['middleware'] ?? [];
            $requiredCapabilities = [];

            foreach ($routeMiddleware as $middleware) {
                $middlewareSeparator = 'RequireIntegrationCapability:';

                if (str_contains($middleware, $middlewareSeparator)) {
                    $requiredCapabilities[] = substr($middleware, strpos($middleware, $middlewareSeparator) + strlen($middlewareSeparator));
                }
            }

            expect($operation)->toHaveKey('x-required-capability')
                ->and($operation['x-required-capability'])->toBe(
                    count($requiredCapabilities) === 1 ? $requiredCapabilities[0] : null,
                );
        }
    }
});

it('advertises exactly the operations provided by the capability catalog and a live endpoint', function () {
    $document = integrationOpenApiDocument();
    $catalogOperations = app(IntegrationCapabilityCatalog::class)->operations();
    $advertisedOperations = $document['paths']['/api/v1/operations']['post']['requestBody']['content']['application/json']['schema']['discriminator']['mapping'];
    $advertisedNames = array_keys($advertisedOperations);
    $catalogNames = array_column($catalogOperations, 'operation');
    $runtimeRoutes = integrationRouteContract();

    sort($advertisedNames);
    sort($catalogNames);

    expect($advertisedNames)->toBe($catalogNames);

    foreach ($catalogOperations as $operation) {
        $uri = $operation['path'];
        $method = strtoupper($operation['method']);

        expect($runtimeRoutes[$uri]['methods'] ?? [])->toContain($method)
            ->and($advertisedOperations[$operation['operation']] ?? null)
            ->toBeString()
            ->and($operation['capability'])->toBe('operations:propose');
    }
});

it('advertises only proposal operations accepted by the runtime request allowlist', function (): void {
    $catalogNames = array_column(app(IntegrationCapabilityCatalog::class)->operations(), 'operation');

    expect($catalogNames)->not->toBeEmpty();

    foreach ($catalogNames as $operation) {
        expect(ProposeServiceOperationRequest::supportsExternalOperation($operation))->toBeTrue();
    }
});

/** @return array<string, mixed> */
function openApiContractSchema(array $document, string $name): array
{
    $schema = $document['components']['schemas'][$name] ?? null;

    expect($schema)->toBeArray();

    return $schema;
}

/** @return array<string, mixed> */
function openApiResolveSchema(array $schema, array $document): array
{
    if (isset($schema['$ref'])) {
        $name = Str::afterLast((string) $schema['$ref'], '/');

        return openApiContractSchema($document, $name);
    }

    return $schema;
}

function assertOpenApiResponseShape(mixed $value, array $schema, array $document, string $path = 'data'): void
{
    $schema = openApiResolveSchema($schema, $document);

    foreach ($schema['allOf'] ?? [] as $part) {
        assertOpenApiResponseShape($value, $part, $document, $path);
    }

    $types = is_array($schema['type'] ?? null) ? $schema['type'] : [$schema['type'] ?? null];

    if (in_array('object', $types, true)) {
        expect($value, $path)->toBeArray();

        foreach ($schema['required'] ?? [] as $required) {
            expect($value, $path)->toHaveKey($required);
        }

        if (($schema['additionalProperties'] ?? null) === false) {
            expect(array_diff(array_keys($value), array_keys($schema['properties'] ?? [])), $path)
                ->toBe([]);
        }

        foreach ($schema['properties'] ?? [] as $property => $propertySchema) {
            if (array_key_exists($property, $value)) {
                assertOpenApiResponseShape($value[$property], $propertySchema, $document, $path.'.'.$property);
            }
        }

        return;
    }

    if (in_array('array', $types, true)) {
        expect($value, $path)->toBeArray();

        foreach ($value as $index => $item) {
            if (isset($schema['items']) && is_array($schema['items'])) {
                assertOpenApiResponseShape($item, $schema['items'], $document, $path.'.'.$index);
            }
        }
    }
}

function assertNoExternalIntegrationData(mixed $value, string $path = 'data'): void
{
    $forbidden = [
        'category_id',
        'expected_version',
        'last_page',
        'lock_version',
        'professional_id',
        'service_id',
        'total',
        'contact',
        'customer',
        'description',
        'email',
        'image_path',
        'note',
        'notes',
        'phone',
        'sale',
        'sales',
    ];

    if (is_array($value)) {
        foreach ($value as $key => $child) {
            expect($forbidden, $path)->not->toContain((string) $key);
            assertNoExternalIntegrationData($child, $path.'.'.$key);
        }
    }
}

/** @return array{0: User, 1: Tenant, 2: Unit, 3: Category, 4: Service, 5: Professional, 6: array<string, string>} */
function openApiContractWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'OpenAPI contract '.Str::random(8),
        'slug' => 'openapi-contract-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Cortes',
        'type' => 'service',
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'category_id' => $category->getKey(),
        'name' => 'Corte clássico',
        'duration_minutes' => 35,
        'price_cents' => 4500,
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'João Barbeiro',
    ]);
    $professional->services()->attach($service, [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    $secrets = [];
    foreach (['catalog:read', 'setup:read', 'operations:propose'] as $capability) {
        $issued = $owner->createToken('OpenAPI contract test', [$capability]);
        $passportToken = $issued->getToken();
        IntegrationCredential::query()->create([
            'id' => (string) Str::uuid(),
            'passport_token_id' => $passportToken->getKey(),
            'user_id' => $owner->getKey(),
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'label' => 'OpenAPI contract test',
            'capabilities' => [$capability],
            'expires_at' => $passportToken->expires_at,
        ]);
        $secrets[$capability] = $issued->accessToken;
    }

    return [$owner, $tenant, $unit, $category, $service, $professional, $secrets];
}

it('keeps live catalog, setup, proposal, and proposal status bodies within their OpenAPI schemas', function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(openApiContractPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('OpenAPI contract tests', 'users');

    [$owner, $tenant, $unit, $category, $service, $professional, $secrets] = openApiContractWorkspace();
    $document = integrationOpenApiDocument();

    $catalogResponses = [
        'categories' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/categories'),
        'services' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/services'),
        'professionals' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/professionals'),
        'category' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/categories/'.$category->getKey()),
        'service' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/services/'.$service->getKey()),
        'professional' => $this->withToken($secrets['catalog:read'])->getJson('/api/v1/professionals/'.$professional->getKey()),
    ];
    $catalogSchemas = [
        'categories' => 'CategoryCollectionResponse',
        'services' => 'ServiceCollectionResponse',
        'professionals' => 'ProfessionalCollectionResponse',
        'category' => 'CategoryResponse',
        'service' => 'ServiceResponse',
        'professional' => 'ProfessionalResponse',
    ];

    foreach ($catalogResponses as $name => $response) {
        $response->assertSuccessful();
        assertOpenApiResponseShape($response->json(), openApiContractSchema($document, $catalogSchemas[$name]), $document);
        assertNoExternalIntegrationData($response->json());
    }

    expect($catalogResponses['categories']->json('data.0'))->toBe(['name' => 'Cortes'])
        ->and($catalogResponses['services']->json('data.0'))->toBe([
            'name' => 'Corte clássico',
            'duration_minutes' => 35,
            'price_cents' => 4500,
        ])
        ->and($catalogResponses['professionals']->json('data.0'))->toBe(['name' => 'João Barbeiro']);

    app('auth')->forgetGuards();
    $setup = $this->withToken($secrets['setup:read'])->getJson('/api/v1/setup/status');
    $setup->assertSuccessful();
    assertOpenApiResponseShape($setup->json(), openApiContractSchema($document, 'SetupStatusResponse'), $document);
    assertNoExternalIntegrationData($setup->json());

    app('auth')->forgetGuards();
    $proposal = $this->withToken($secrets['operations:propose'])
        ->withHeader('X-Idempotency-Key', 'openapi-contract-service-create')
        ->postJson('/api/v1/operations', [
            'operation' => 'service.create',
            'input' => [
                'name' => 'Corte degradê',
                'duration_minutes' => 45,
                'price_cents' => 6000,
            ],
        ])
        ->assertStatus(202);
    assertOpenApiResponseShape($proposal->json(), openApiContractSchema($document, 'OperationProposalResponse'), $document);
    expect(array_keys($proposal->json('data')))->toBe([
        'id', 'operation', 'status', 'confirmation_url', 'expires_at', 'summary', 'changes',
    ])
        ->and($proposal->json('data.summary'))->toBe(['changed_fields' => ['name', 'duration_minutes', 'price_cents']])
        ->and($proposal->json('data.changes'))->toBe(['changed_fields' => ['name', 'duration_minutes', 'price_cents']]);
    assertNoExternalIntegrationData($proposal->json('data.summary'));
    assertNoExternalIntegrationData($proposal->json('data.changes'));

    app('auth')->forgetGuards();
    $status = $this->withToken($secrets['operations:propose'])->getJson('/api/v1/operations/'.$proposal->json('data.id'))->assertSuccessful();
    assertOpenApiResponseShape($status->json(), openApiContractSchema($document, 'OperationProposalStatusResponse'), $document);
    expect(array_keys($status->json('data')))->toBe([
        'id', 'operation', 'status', 'confirmation_url', 'expires_at', 'summary',
    ])
        ->and($status->json('data.id'))->toBe($proposal->json('data.id'))
        ->and($status->json('data.summary'))->toBe(['changed_fields' => ['name', 'duration_minutes', 'price_cents']]);
    assertNoExternalIntegrationData($status->json('data.summary'));
});

function openApiContractPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/openapi-contract-passport-'.Str::uuid();
        mkdir($directory, 0700, true);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        expect($key)->not->toBeFalse();
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        file_put_contents($directory.'/oauth-private.key', $privateKey);
        file_put_contents($directory.'/oauth-public.key', $details['key']);
        chmod($directory.'/oauth-private.key', 0600);
        chmod($directory.'/oauth-public.key', 0600);
    }

    return $directory;
}
