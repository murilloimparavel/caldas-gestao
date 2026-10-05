<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Category;
use App\Models\Integrations\ProposedOperation;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['assistant.enabled' => true]);
});

/** @return array{owner: User, tenant: Tenant, unit: Unit} */
function internalAssistantWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Assistant '.Str::random(8),
        'slug' => 'assistant-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(14),
    ]);

    return compact('owner', 'tenant', 'unit');
}

function groqResponse(array $message): array
{
    return ['choices' => [['message' => $message]]];
}

function assistantHeaders(Tenant $tenant, Unit $unit): array
{
    return ['X-Tenant-Id' => $tenant->getKey(), 'X-Unit-Id' => $unit->getKey()];
}

it('exposes the internal assistant only to an eligible administrator', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => null]);

    $this->actingAs($owner)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->get(route('assistant.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('assistant/index')
            ->where('conversation', null)
            ->where('workspace.unitName', $unit->name)
            ->where('limits.available', false));

    $collaborator = User::factory()->create();
    $membership = Membership::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $collaborator->getKey(),
        'status' => 'active',
        'joined_at' => now(),
    ]);
    MembershipUnit::factory()->forMembership($membership)->forUnit($unit)->create(['is_primary' => true]);

    $this->actingAs($collaborator)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->get(route('assistant.index'))
        ->assertForbidden();
});

it('stops every assistant route before any provider or database side effect when disabled', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.enabled' => false, 'assistant.groq.api_key' => 'groq-test-key']);
    Http::preventStrayRequests();
    Http::fake();

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->get(route('assistant.index'))->assertNotFound();
    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.conversations.store'))->assertNotFound();
    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->get(route('assistant.show', $conversation))->assertNotFound();
    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Leia o catálogo'])
        ->assertNotFound();
    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->delete(route('assistant.destroy', $conversation))->assertNotFound();

    expect(AssistantConversation::query()->count())->toBe(1)
        ->and($conversation->messages()->count())->toBe(0)
        ->and(ProposedOperation::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('persists a redacted conversation and sends only approved context to Groq', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.groq.com/*' => Http::response(groqResponse([
            'role' => 'assistant',
            'content' => 'Seu catálogo está pronto para revisão.',
        ])),
    ]);

    $this->actingAs($owner)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.conversations.store'))
        ->assertRedirect();
    $conversation = AssistantConversation::query()->where('user_id', $owner->getKey())->firstOrFail();

    $this->actingAs($owner)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), [
            'content' => 'Verifique o catálogo de teste email@example.com e telefone (35) 99999-1234, CPF 123.456.789-09.',
        ])
        ->assertRedirect(route('assistant.show', $conversation));

    $userMessage = $conversation->messages()->where('role', 'user')->firstOrFail();
    expect($userMessage->content)
        ->not->toContain('email@example.com')
        ->not->toContain('99999-1234')
        ->not->toContain('123.456.789-09');
    expect($conversation->fresh()?->title)->toBeNull();
    expect($conversation->messages()->where('role', 'assistant')->count())->toBe(1);

    Http::assertSent(function (Request $request) use ($unit): bool {
        $messages = $request->data()['messages'] ?? [];
        $encoded = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $request->hasHeader('Authorization', 'Bearer groq-test-key')
            && is_string($encoded)
            && ! str_contains($encoded, 'email@example.com')
            && ! str_contains($encoded, '99999-1234')
            && ! str_contains($encoded, '123.456.789-09')
            && ! str_contains($encoded, $unit->name);
    });
});

it('executes an approved catalog read tool and never exposes disallowed tools', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-catalog',
                'type' => 'function',
                'function' => ['name' => 'read_catalog', 'arguments' => '{"kind":"services"}'],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Encontrei os serviços cadastrados.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Quais serviços temos?'])
        ->assertRedirect();

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);
    $firstTools = $requests[0][0]->data()['tools'] ?? [];
    $toolNames = collect($firstTools)->map(fn (array $tool): string => (string) ($tool['function']['name'] ?? ''))->all();
    expect($toolNames)->toContain('read_catalog')->toContain('read_setup_status')->toContain('propose_operation')
        ->not->toContain('read_customers')->not->toContain('execute_operation');
    $proposalTool = collect($firstTools)->first(fn (array $tool): bool => ($tool['function']['name'] ?? null) === 'propose_operation');
    expect($proposalTool['function']['parameters']['properties']['operation']['enum'] ?? [])->toBe([
        'category.create', 'category.update', 'professional.create', 'professional.update', 'service.create', 'service.update', 'unit.update',
    ]);
    expect(json_encode($proposalTool['function']['parameters']['properties']['input']['oneOf'] ?? [], JSON_THROW_ON_ERROR))
        ->not->toContain('address');
    $readTool = collect($firstTools)->first(fn (array $tool): bool => ($tool['function']['name'] ?? null) === 'read_catalog');
    expect($readTool['function']['parameters']['properties']['page'] ?? null)->toBe(['type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1])
        ->and($readTool['function']['parameters']['properties']['per_page']['maximum'] ?? null)->toBe(100);
    expect(json_decode($conversation->messages()->where('role', 'tool')->firstOrFail()->content, true)['pagination']['has_more'])->toBeFalse();
    Http::assertSent(fn (Request $request): bool => ! str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR), $unit->name));
});

it('paginates internal catalog reads and marks a final response partial when more pages remain', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    Service::factory()->count(101)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'catalog-page-one',
                'type' => 'function',
                'function' => ['name' => 'read_catalog', 'arguments' => '{"kind":"services","page":1,"per_page":100}'],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Estes são todos os serviços.']))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Encontrei os serviços.']));
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Liste os serviços'])
        ->assertRedirect();

    $toolContent = $conversation->messages()->where('role', 'tool')->value('content');
    expect(json_decode($toolContent, true)['pagination'])->toBe([
        'current_page' => 1,
        'per_page' => 100,
        'has_more' => true,
    ]);
    expect($conversation->messages()->where('role', 'assistant')->latest('id')->value('content'))
        ->toContain('A leitura do catálogo ficou parcial')
        ->toContain('não trate esta lista como completa');
    Http::assertSent(fn (Request $request): bool => ! str_contains(json_encode($request->data(), JSON_THROW_ON_ERROR), $unit->name));
});

it('allows the internal assistant to request the next catalog page', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    Service::factory()->count(101)->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'catalog-page-one',
                'type' => 'function',
                'function' => ['name' => 'read_catalog', 'arguments' => '{"kind":"services","page":1,"per_page":100}'],
            ]],
        ]))
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'catalog-page-two',
                'type' => 'function',
                'function' => ['name' => 'read_catalog', 'arguments' => '{"kind":"services","page":2,"per_page":100}'],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Li os serviços do catálogo.']));
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Liste todos os serviços'])
        ->assertRedirect();

    $pagination = $conversation->messages()->where('role', 'tool')->orderBy('id')->pluck('content')
        ->map(static fn (string $content): array => json_decode($content, true)['pagination'])
        ->all();
    expect($pagination)->toBe([
        ['current_page' => 1, 'per_page' => 100, 'has_more' => true],
        ['current_page' => 2, 'per_page' => 100, 'has_more' => false],
    ]);
    $replayedMessages = Http::recorded()[1][0]->data()['messages'];
    $replayedToolContent = collect($replayedMessages)->firstWhere('role', 'tool')['content'] ?? '';
    expect(json_decode($replayedToolContent, true)['pagination']['has_more'])->toBeTrue()
        ->and(json_encode($replayedMessages, JSON_THROW_ON_ERROR))->not->toContain($unit->name);
});

it('turns valid write requests into pending proposals without executing them', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-proposal',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => '{"operation":"category.create","input":{"name":"Barba","type":"service"}}',
                ],
            ]],
        ]))
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-proposal',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => '{"operation":"category.create","input":{"name":"Barba","type":"service"}}',
                ],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Preparei uma proposta para sua revisão.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)
        ->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Crie uma categoria Barba'])
        ->assertRedirect()
        ->assertSessionHas('proposal_ids', fn (array $proposalIds): bool => count($proposalIds) === 1);

    expect(Category::query()->where('tenant_id', $tenant->getKey())->where('name', 'Barba')->exists())->toBeFalse();
    $proposal = ProposedOperation::query()
        ->where('actor_id', $owner->getKey())
        ->where('source', 'internal_assistant')
        ->firstOrFail();
    expect($proposal->status)->toBe('pending_confirmation')
        ->and($proposal->operation_key)->toBe('category.create');
    $proposalId = (string) $proposal->getKey();
    $toolMessage = $conversation->messages()->where('role', 'tool')->firstOrFail();
    expect($toolMessage->content)->not->toContain($proposalId);
    foreach (Http::recorded() as [$request]) {
        expect(json_encode($request->data(), JSON_THROW_ON_ERROR))->not->toContain($proposalId);
    }
    expect(json_encode($conversation->messages()->where('role', 'assistant')->firstOrFail()->metadata))
        ->not->toContain('99999-1234');
    expect(ProposedOperation::query()->where('actor_id', $owner->getKey())->count())->toBe(1);
});

it('rejects unit address arguments before persistence and provider replay', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    $address = 'Rua confidencial 123';
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-address',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => json_encode([
                        'operation' => 'unit.update',
                        'input' => [
                            'name' => 'Barbearia',
                            'timezone' => 'America/Sao_Paulo',
                            'address' => ['street' => $address],
                            'online_booking_enabled' => false,
                            'appointment_sales_automation_enabled' => false,
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Endereço não é aceito nesta conversa.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Atualize a unidade'])
        ->assertRedirect();

    expect(ProposedOperation::query()->where('actor_id', $owner->getKey())->exists())->toBeFalse();
    expect($conversation->messages()->where('role', 'tool')->pluck('content')->implode(' '))->not->toContain($address);
    expect(json_encode(Http::recorded()[1][0]->data(), JSON_THROW_ON_ERROR))->not->toContain($address);
    expect(json_encode($conversation->messages()->where('role', 'assistant')->firstOrFail()->metadata, JSON_THROW_ON_ERROR))->not->toContain($address);
});

it('resolves exact professional names privately while sanitizing the replay payload', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'name' => 'Barbeiro IA',
    ]);
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-service',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => json_encode([
                        'operation' => 'service.create',
                        'input' => [
                            'name' => 'Corte clássico',
                            'duration_minutes' => 45,
                            'price_cents' => 6000,
                            'professional_names' => [$professional->name],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Preparei uma proposta.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Proponha um serviço'])
        ->assertRedirect();

    $proposal = ProposedOperation::query()->where('actor_id', $owner->getKey())->firstOrFail();
    expect($proposal->input['professional_ids'])->toBe([(string) $professional->getKey()]);
    expect(json_encode(Http::recorded()[1][0]->data(), JSON_THROW_ON_ERROR))
        ->toContain($professional->name)
        ->not->toContain((string) $professional->getKey());
});

it('rejects sensitive tool arguments without persisting or replaying their content', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    $sensitive = 'email@example.com';
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-sensitive',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => json_encode([
                        'operation' => 'category.create',
                        'input' => ['name' => $sensitive, 'type' => 'service'],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Não posso usar dados pessoais.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Use esta descrição'])
        ->assertRedirect();

    expect(ProposedOperation::query()->where('actor_id', $owner->getKey())->exists())->toBeFalse();
    expect($conversation->messages()->where('role', 'tool')->pluck('content')->implode(' '))
        ->toContain('dados sensíveis')
        ->not->toContain($sensitive);
    expect(json_encode(Http::recorded()[1][0]->data(), JSON_THROW_ON_ERROR))->not->toContain($sensitive);
    expect(json_encode($conversation->messages()->where('role', 'assistant')->firstOrFail()->metadata, JSON_THROW_ON_ERROR))
        ->not->toContain($sensitive);
});

it('rejects free description arguments before persistence and provider replay', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    $description = 'Texto livre que não pode entrar na proposta';
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [[
                'id' => 'call-description',
                'type' => 'function',
                'function' => [
                    'name' => 'propose_operation',
                    'arguments' => json_encode([
                        'operation' => 'category.create',
                        'input' => ['name' => 'Barba', 'type' => 'service', 'description' => $description],
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Descrição não é aceita nesta conversa.']));

    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Use esta descrição'])
        ->assertRedirect();

    expect(ProposedOperation::query()->where('actor_id', $owner->getKey())->exists())->toBeFalse();
    expect($conversation->messages()->where('role', 'tool')->pluck('content')->implode(' '))
        ->toContain('não são permitidos')
        ->not->toContain($description);
    expect(json_encode(Http::recorded()[1][0]->data(), JSON_THROW_ON_ERROR))->not->toContain($description);
    expect(json_encode($conversation->messages()->where('role', 'assistant')->firstOrFail()->metadata, JSON_THROW_ON_ERROR))
        ->not->toContain($description);
});

it('handles unknown and malformed tool calls as safe tool errors', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fakeSequence()
        ->push(groqResponse([
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call-unknown', 'type' => 'function', 'function' => ['name' => 'delete_everything', 'arguments' => '{}']],
                ['id' => 'call-malformed', 'type' => 'function', 'function' => ['name' => 'read_catalog', 'arguments' => '{broken']],
            ],
        ]))
        ->push(groqResponse(['role' => 'assistant', 'content' => 'Não posso executar essas ações.']));
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Faça isso'])
        ->assertRedirect();

    expect($conversation->messages()->where('role', 'tool')->count())->toBe(2);
    expect($conversation->messages()->where('role', 'tool')->pluck('content')->implode(' '))
        ->toContain('não autorizada')
        ->toContain('inválidos');
});

it('filters intermediate tool messages and explains when the tool round limit is reached', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    $toolResponse = groqResponse([
        'role' => 'assistant',
        'content' => '',
        'tool_calls' => [[
            'id' => 'call-repeat',
            'type' => 'function',
            'function' => ['name' => 'read_catalog', 'arguments' => '{"kind":"services"}'],
        ]],
    ]);
    Http::fakeSequence()
        ->push($toolResponse)
        ->push($toolResponse)
        ->push($toolResponse);
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Leia o catálogo'])
        ->assertRedirect();

    expect($conversation->messages()->where('role', 'assistant')->where('content', '')->count())->toBe(2)
        ->and($conversation->messages()->where('role', 'assistant')->where('content', 'like', '%limite de etapas%')->value('content'))
        ->toContain('limite de etapas');
});

it('keeps the user message and returns gracefully on provider failures', function (int $status): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key', 'assistant.groq.timeout' => 1]);
    Http::fake(['https://api.groq.com/*' => Http::response(['error' => 'unavailable'], $status)]);
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Verifique o setup'])
        ->assertRedirect(route('assistant.show', $conversation))
        ->assertSessionHas('error');

    expect($conversation->messages()->where('role', 'user')->count())->toBe(1)
        ->and($conversation->messages()->where('role', 'assistant')->count())->toBe(0);
})->with([429, 500]);

it('handles provider connection failures without creating an assistant response', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    config(['assistant.groq.api_key' => 'groq-test-key']);
    Http::fake(['https://api.groq.com/*' => Http::failedConnection()]);
    $conversation = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->withHeaders(assistantHeaders($tenant, $unit))
        ->post(route('assistant.messages.store', $conversation), ['content' => 'Verifique o setup'])
        ->assertRedirect(route('assistant.show', $conversation))
        ->assertSessionHas('error');

    expect($conversation->messages()->where('role', 'user')->count())->toBe(1)
        ->and($conversation->messages()->where('role', 'assistant')->count())->toBe(0);
});

it('deletes expired conversations with the retention command', function (): void {
    ['owner' => $owner, 'tenant' => $tenant, 'unit' => $unit] = internalAssistantWorkspace();
    $expired = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->subMinute(),
    ]);
    $active = AssistantConversation::query()->create([
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'expires_at' => now()->addDay(),
    ]);
    $expired->messages()->create(['role' => 'user', 'content' => 'apagada']);

    $this->artisan('assistant:purge-conversations')->assertSuccessful();

    expect(AssistantConversation::query()->whereKey($expired->getKey())->exists())->toBeFalse()
        ->and(AssistantConversation::query()->whereKey($active->getKey())->exists())->toBeTrue()
        ->and(AssistantMessage::query()->where('conversation_id', $expired->getKey())->exists())->toBeFalse();
});
