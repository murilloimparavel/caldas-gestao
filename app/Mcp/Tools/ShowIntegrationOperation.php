<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\IntegrationMcpContextResolver;
use App\Mcp\ProposalMetadata;
use App\Mcp\ProposedOperationRepository;
use App\Support\Integrations\ProposedOperationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read the status and expiry of an integration proposal created by this authenticated integration. Payload values, snapshots, and resource details are never returned.')]
#[IsReadOnly]
#[IsIdempotent]
final class ShowIntegrationOperation extends Tool
{
    public function handle(Request $request, IntegrationMcpContextResolver $resolver, ProposedOperationRepository $repository, ProposedOperationService $operations): ResponseFactory
    {
        $context = $resolver->resolve()->requireCapability('operations:propose');
        $proposalId = $request->get('proposal_id');

        if (! is_string($proposalId) || ! Str::isUuid($proposalId)) {
            throw ValidationException::withMessages([
                'proposal_id' => ['The proposal_id field must be a valid UUID.'],
            ]);
        }

        $proposal = $repository->findOrFail($proposalId);
        $proposal = $operations->show($proposal, $context->user, $context->tenantContext);

        return Response::structured([
            'data' => ProposalMetadata::from($proposal),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'proposal_id' => $schema->string()
                ->format('uuid')
                ->description('The proposal UUID returned by propose_integration_operation.')
                ->required(),
        ];
    }
}
