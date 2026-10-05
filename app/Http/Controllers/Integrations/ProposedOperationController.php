<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Calendar\CreateAvailabilityRule;
use App\Actions\Calendar\CreateScheduleBlock;
use App\Actions\Calendar\DeleteAvailabilityRule;
use App\Actions\Calendar\DeleteScheduleBlock;
use App\Actions\Calendar\UpdateAvailabilityRule;
use App\Actions\Calendar\UpdateScheduleBlock;
use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\UpdateCategory;
use App\Actions\Identity\UpdateUnitSettings;
use App\Actions\Integration\ExecuteProfessionalConfigurationOperation;
use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Actions\OnlineBooking\UnpublishOnlineBookingSite;
use App\Actions\OnlineBooking\UpdateOnlineBookingSettings;
use App\Actions\Services\CreateService;
use App\Actions\Services\UpdateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\ProposeServiceOperationRequest;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\User;
use App\Support\Integrations\ProposalConfirmationUrl;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProposedOperationController extends Controller
{
    public function propose(ProposeServiceOperationRequest $request, ProposedOperationService $operations): JsonResponse
    {
        $key = trim((string) $request->header('X-Idempotency-Key', ''));
        abort_if($key === '' || mb_strlen($key) > 200, 422, 'A valid X-Idempotency-Key header is required.');

        $context = $this->context($request);
        $credential = $request->attributes->get(IntegrationCredential::class);
        abort_unless($credential instanceof IntegrationCredential, 403);
        $validated = $request->validated();
        $proposal = $operations->propose($credential, $context, $validated['operation'], $validated['input'], $key);
        $summary = $operations->externalSummary($proposal);

        return response()->json([
            'data' => [
                'id' => (string) $proposal->getKey(),
                'operation' => $proposal->operation_key,
                'status' => $proposal->status,
                'confirmation_url' => ProposalConfirmationUrl::for($proposal),
                'expires_at' => $proposal->expires_at->toISOString(),
                'summary' => $summary,
                'changes' => $summary,
            ],
        ], 202)->header('Cache-Control', 'no-store, private');
    }

    public function apiShow(Request $request, ProposedOperation $operation, ProposedOperationService $operations): JsonResponse
    {
        $operation = $operations->show($operation, $this->actor($request), $this->context($request));
        $summary = $operations->externalSummary($operation);

        return response()->json([
            'data' => [
                'id' => (string) $operation->getKey(),
                'operation' => $operation->operation_key,
                'status' => $operation->status,
                'confirmation_url' => ProposalConfirmationUrl::for($operation),
                'expires_at' => $operation->expires_at->toISOString(),
                'summary' => $summary,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, ProposedOperation $proposal, ProposedOperationService $operations): InertiaResponse
    {
        $context = $this->context($request);
        $proposal = $operations->show($proposal, $this->actor($request), $context);
        $reviewDetails = in_array($proposal->operation_key, ['professional.create', 'professional.update', 'service.create', 'service.update'], true)
            ? $operations->reviewDetails($proposal, $context)
            : [
                'professionals' => [],
                'professionalAssignmentsValid' => true,
                'serviceAssignmentsValid' => true,
                'currentServices' => [],
                'proposedServices' => [],
            ];
        $currentCategory = $operations->categoryForReview($proposal, $context);
        $proposedCategory = $operations->proposedCategoryForReview($proposal, $context);
        $expectedCategoryVersionMatches = $currentCategory !== null
            && $currentCategory['lock_version'] === (int) ($proposal->input['expected_version'] ?? 0);
        $currentService = $operations->serviceForReview($proposal, $context);
        $proposedService = $operations->proposedServiceForReview($proposal, $context);
        $expectedServiceVersionMatches = $currentService !== null
            && $currentService['lock_version'] === (int) ($proposal->input['expected_version'] ?? 0);
        $unitReview = $operations->unitForReview($proposal, $context);
        $operationReview = $operations->operationReview($proposal, $context);
        $serviceRelationReview = $proposal->operation_key === 'service.update'
            ? $operations->serviceRelationsForReview($proposal, $context)
            : null;
        $professionalServiceReview = $proposal->operation_key === 'professional.update'
            ? [
                'currentServices' => array_map(static fn (array $service): array => ['name' => $service['name']], $reviewDetails['currentServices']),
                'proposedServices' => array_map(static fn (array $service): array => ['name' => $service['name']], $reviewDetails['proposedServices']),
            ]
            : null;
        $summary = [
            ...$operations->summary($proposal, $context),
            'professionals' => $reviewDetails['professionals'],
            'professionalAssignmentsValid' => $reviewDetails['professionalAssignmentsValid'],
        ];

        return Inertia::render('settings/integrations/proposals/show', [
            'proposal' => [
                'id' => (string) $proposal->getKey(),
                'operation' => $proposal->operation_key,
                'status' => $proposal->status,
                'expiresAt' => $proposal->expires_at->toISOString(),
                'createdAt' => $proposal->created_at?->toISOString(),
                'summary' => $summary,
                'currentCategory' => $currentCategory,
                'proposedCategory' => $proposedCategory,
                'expectedCategoryVersionMatches' => $expectedCategoryVersionMatches,
                'currentService' => $currentService,
                'proposedService' => $proposedService,
                'expectedServiceVersionMatches' => $expectedServiceVersionMatches,
                'currentUnit' => $unitReview['unit'],
                'expectedUnitVersionMatches' => $unitReview['expectedVersionMatches'],
                'operationReview' => $operationReview,
                'serviceRelations' => $serviceRelationReview,
                'professionalServices' => $professionalServiceReview,
                'changes' => $proposal->operation_key === 'professional.update'
                    ? ['current' => $operationReview['current'], 'proposed' => $operationReview['proposed']]
                    : [...$proposal->input, ...$reviewDetails],
                'canConfirm' => $proposal->status === ProposedOperation::STATUS_PENDING_CONFIRMATION
                    && $proposal->expires_at->isFuture()
                    && $reviewDetails['professionalAssignmentsValid']
                    && $reviewDetails['serviceAssignmentsValid']
                    && ($proposal->operation_key !== 'category.update' || $expectedCategoryVersionMatches)
                    && ($proposal->operation_key !== 'service.update' || ($expectedServiceVersionMatches && ($serviceRelationReview['assignmentsValid'] ?? false)))
                    && ($proposal->operation_key !== 'unit.update' || $unitReview['expectedVersionMatches'])
                    && $operationReview['expectedVersionMatches'],
            ],
        ]);
    }

    public function confirm(Request $request, ProposedOperation $proposal, ProposedOperationService $operations, CreateService $createService, CreateCategory $createCategory, UpdateCategory $updateCategory, UpdateService $updateService, UpdateUnitSettings $updateUnitSettings, CreateAvailabilityRule $createAvailabilityRule, UpdateAvailabilityRule $updateAvailabilityRule, DeleteAvailabilityRule $deleteAvailabilityRule, CreateScheduleBlock $createScheduleBlock, UpdateScheduleBlock $updateScheduleBlock, DeleteScheduleBlock $deleteScheduleBlock, UpdateOnlineBookingSettings $updateOnlineBookingSettings, SaveOnlineBookingDraft $saveOnlineBookingDraft, PublishOnlineBookingSite $publishOnlineBookingSite, UnpublishOnlineBookingSite $unpublishOnlineBookingSite, ExecuteProfessionalConfigurationOperation $professionalConfiguration): Response
    {
        $proposal = $operations->confirm($proposal, $this->actor($request), $this->context($request), $createService, $createCategory, $updateCategory, $updateService, $updateUnitSettings, $createAvailabilityRule, $updateAvailabilityRule, $deleteAvailabilityRule, $createScheduleBlock, $updateScheduleBlock, $deleteScheduleBlock, $updateOnlineBookingSettings, $saveOnlineBookingDraft, $publishOnlineBookingSite, $unpublishOnlineBookingSite, $professionalConfiguration);

        return to_route('integration-proposals.show', $proposal)->with('success', 'A operação foi executada.');
    }

    public function reject(Request $request, ProposedOperation $proposal, ProposedOperationService $operations): Response
    {
        $proposal = $operations->reject($proposal, $this->actor($request), $this->context($request));

        return to_route('integration-proposals.show', $proposal)->with('success', 'A proposta foi rejeitada.');
    }

    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);
        abort_unless($context instanceof TenantContext, 403);
        Gate::authorize('manage-integrations');

        return $context;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
