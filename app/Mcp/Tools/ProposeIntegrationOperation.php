<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Requests\Integrations\ProposeServiceOperationRequest;
use App\Mcp\IntegrationMcpContextResolver;
use App\Mcp\ProposalMetadata;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Create a pending category, professional, service, or unit settings proposal for an administrator to review. This tool never applies a change and never returns submitted values.')]
#[IsReadOnly(false)]
#[IsIdempotent]
final class ProposeIntegrationOperation extends Tool
{
    public function __construct(private IntegrationCapabilityCatalog $catalog = new IntegrationCapabilityCatalog) {}

    public function handle(Request $request, IntegrationMcpContextResolver $resolver, ProposedOperationService $operations): ResponseFactory
    {
        $context = $resolver->resolve()->requireCapability('operations:propose');
        $operation = $request->get('operation');
        $input = $request->get('input');
        $idempotencyKey = $request->get('idempotency_key');

        if (! is_string($operation) || ! is_array($input) || ! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            throw ValidationException::withMessages([
                'operation' => ['The operation, input, and idempotency_key fields are required.'],
            ]);
        }

        if (mb_strlen(trim($idempotencyKey)) > 200) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The idempotency_key field must not exceed 200 characters.'],
            ]);
        }

        $validated = $this->validateProposal($operation, $input, $context->tenantContext);
        $proposal = $operations->propose(
            $context->credential,
            $context->tenantContext,
            $validated['operation'],
            $validated['input'],
            trim($idempotencyKey),
        );

        return Response::structured([
            'data' => ProposalMetadata::from($proposal),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $operations = array_column($this->catalog->operations(), 'operation');

        return [
            'operation' => $schema->string()
                ->enum($operations)
                ->description('The approved runtime operation to propose.')
                ->required(),
            'input' => $schema->object()
                ->description('The operation input matching the schema returned by list_integration_capabilities.')
                ->required(),
            'idempotency_key' => $schema->string()
                ->min(1)
                ->max(200)
                ->description('A caller-generated key reused for safe retries of the same proposal.')
                ->required(),
        ];
    }

    /** @return array<string, mixed> */
    /** @param array<string, mixed> $input
     * @return array{operation: string, input: array<string, mixed>}
     */
    private function validateProposal(string $operation, array $input, TenantContext $context): array
    {
        $validationRequest = ProposeServiceOperationRequest::create(
            '/api/v1/operations',
            'POST',
            ['operation' => $operation, 'input' => $input],
        );
        $validationRequest->setUserResolver(fn () => request()->user());
        $validationRequest->attributes->set(TenantContext::class, $context);

        $validated = Validator::make($validationRequest->all(), $validationRequest->rules())->validate();
        $validatedOperation = $validated['operation'] ?? null;
        $validatedInput = $validated['input'] ?? null;

        if (! is_string($validatedOperation) || ! is_array($validatedInput)) {
            throw ValidationException::withMessages([
                'input' => ['The proposal input did not pass the expected operation schema.'],
            ]);
        }

        $normalizedInput = [];
        foreach ($validatedInput as $key => $value) {
            if (! is_string($key)) {
                throw ValidationException::withMessages([
                    'input' => ['The proposal input keys must be strings.'],
                ]);
            }

            $normalizedInput[$key] = $value;
        }

        return [
            'operation' => $validatedOperation,
            'input' => $normalizedInput,
        ];
    }
}
