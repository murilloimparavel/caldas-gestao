<?php

namespace App\Support\Assistant;

use App\Http\Requests\Integrations\ProposeServiceOperationRequest;
use App\Models\User;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use App\Support\Integrations\IntegrationCatalogQuery;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Validator;

final class AssistantToolExecutor
{
    public function __construct(
        private readonly IntegrationCatalogQuery $catalog,
        private readonly IntegrationCapabilityCatalog $capabilities,
        private readonly ProposedOperationService $proposals,
    ) {}

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        $operations = $this->supportedOperations();

        return [
            ['type' => 'function', 'function' => ['name' => 'read_catalog', 'description' => 'Leia uma página de categorias, serviços e profissionais, incluindo preço e duração dos serviços. Confira pagination.has_more; quando true, leia as páginas seguintes e nunca apresente a primeira página como catálogo completo.', 'parameters' => ['type' => 'object', 'additionalProperties' => false, 'properties' => ['kind' => ['type' => 'string', 'enum' => ['categories', 'services', 'professionals']], 'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1], 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]]]]],
            ['type' => 'function', 'function' => ['name' => 'read_setup_status', 'description' => 'Leia indicadores agregados de prontidão da configuração da unidade.', 'parameters' => ['type' => 'object', 'additionalProperties' => false, 'properties' => []]]],
            ['type' => 'function', 'function' => ['name' => 'propose_operation', 'description' => 'Crie uma proposta pendente para revisão humana. Nunca execute uma alteração. As operações disponíveis e seus campos seguem o catálogo administrativo atual.', 'parameters' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['operation', 'input'], 'properties' => ['operation' => ['type' => 'string', 'enum' => array_column($operations, 'operation')], 'input' => ['oneOf' => array_column($operations, 'input_schema')]]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments, User $user, TenantContext $context, string $idempotencyKey = 'manual'): array
    {
        return match ($name) {
            'read_catalog' => $this->readCatalog($arguments, $context),
            'read_setup_status' => $this->catalog->setupStatus($context),
            'propose_operation' => $this->propose($arguments, $user, $context, $idempotencyKey),
            default => ['error' => 'Ferramenta não autorizada.'],
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function readCatalog(array $arguments, TenantContext $context): array
    {
        $kind = $arguments['kind'] ?? 'services';
        $pageNumber = $this->pageNumber($arguments['page'] ?? 1);
        $perPage = $this->perPage($arguments['per_page'] ?? 50);
        if (! is_string($kind) || ! in_array($kind, ['categories', 'services', 'professionals'], true)) {
            return ['error' => 'O tipo de catálogo é inválido.'];
        }

        $page = match ($kind) {
            'categories' => $this->catalog->categories($context, $perPage, null, $pageNumber),
            'professionals' => $this->catalog->professionals($context, $perPage, null, $pageNumber),
            default => $this->catalog->services($context, $perPage, null, $pageNumber),
        };

        return [
            'items' => array_values($page->items()),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ];
    }

    private function pageNumber(mixed $value): int
    {
        if (! is_int($value) || $value < 1 || $value > 10000) {
            throw new \InvalidArgumentException('The page value must be an integer between 1 and 10000.');
        }

        return $value;
    }

    private function perPage(mixed $value): int
    {
        if (! is_int($value) || $value < 1 || $value > 100) {
            throw new \InvalidArgumentException('The per_page value must be an integer between 1 and 100.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function propose(array $arguments, User $user, TenantContext $context, string $idempotencyKey): array
    {
        $operation = $arguments['operation'] ?? null;
        $input = $arguments['input'] ?? null;
        if (! is_string($operation) || ! is_array($input)) {
            return ['error' => 'Os argumentos da proposta são inválidos.'];
        }

        if (! in_array($operation, array_column($this->supportedOperations(), 'operation'), true)) {
            return ['error' => 'Esta operação não está disponível no assistente.'];
        }

        $request = ProposeServiceOperationRequest::create('/api/v1/operations', 'POST', [
            'operation' => $operation,
            'input' => $input,
        ]);
        $request->setUserResolver(static fn (): User => $user);
        $request->attributes->set(TenantContext::class, $context);

        if (! $request->authorize()) {
            return ['error' => 'Esta operação não está autorizada no contexto atual.'];
        }

        $validator = Validator::make($request->all(), $request->rules());
        if ($validator->fails()) {
            return ['error' => 'Os dados da proposta são inválidos.', 'fields' => $validator->errors()->toArray()];
        }

        $validated = $validator->validated();
        $proposal = $this->proposals->propose($user, $context, $operation, $validated['input'], 'assistant-'.$idempotencyKey);

        return ['proposal_id' => (string) $proposal->getKey(), 'status' => $proposal->status, 'message' => 'Proposta criada para revisão humana.'];
    }

    /**
     * @return list<array{operation: string, description: string, method: string, path: string, capability: string, input_schema: array<string, mixed>}>
     */
    private function supportedOperations(): array
    {
        $supported = ['category.create', 'category.update', 'professional.create', 'professional.update', 'service.create', 'service.update', 'unit.update'];

        return array_values(array_filter(
            array_map($this->assistantOperationDefinition(...), $this->capabilities->operations()),
            static fn (array $operation): bool => in_array($operation['operation'], $supported, true),
        ));
    }

    /**
     * @param  array{operation: string, description: string, method: string, path: string, capability: string, input_schema: array<string, mixed>}  $operation
     * @return array{operation: string, description: string, method: string, path: string, capability: string, input_schema: array<string, mixed>}
     */
    private function assistantOperationDefinition(array $operation): array
    {
        if ($operation['operation'] !== 'unit.update') {
            return $operation;
        }

        $schema = $operation['input_schema'];
        unset($schema['properties']['address']);
        $schema['required'] = array_values(array_diff($schema['required'] ?? [], ['address']));
        $operation['input_schema'] = $schema;

        return $operation;
    }
}
