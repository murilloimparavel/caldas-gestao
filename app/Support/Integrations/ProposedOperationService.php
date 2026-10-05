<?php

namespace App\Support\Integrations;

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
use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\OAuthGrant;
use App\Models\Integrations\ProposedOperation;
use App\Models\OnlineBookingSetting;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Policies\IntegrationAdminPolicy;
use App\Policies\OAuthGrantPolicy;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ProposedOperationService
{
    private const int EXPIRY_MINUTES = 10;

    public function __construct(
        private readonly IntegrationAdminPolicy $integrationAdmin,
        private readonly OAuthGrantPolicy $oauthGrantPolicy,
    ) {}

    /** @param array<string, mixed> $input */
    public function propose(IntegrationCredential|OAuthGrant|User $source, TenantContext $context, string $operation, array $input, string $idempotencyKey): ProposedOperation
    {
        $context = $this->revalidateContext($context);
        $this->assertSourceContext($source, $context);
        $unit = $context->unit;
        if ($unit === null) {
            throw new AuthorizationException('A unit context is required to propose this operation.');
        }

        $requestHash = hash('sha256', json_encode(
            ['operation' => $operation, 'input' => $this->canonicalize($input)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
        $keyHash = hash('sha256', $idempotencyKey);
        $sourceColumn = $this->sourceColumn($source);
        $sourceId = (string) $source->getKey();
        $sourceType = $this->sourceType($source);

        try {
            return DB::transaction(function () use ($sourceColumn, $sourceId, $sourceType, $context, $unit, $operation, $input, $requestHash, $keyHash): ProposedOperation {
                $existing = ProposedOperation::query()
                    ->where('source', $sourceType)
                    ->where($sourceColumn, $sourceId)
                    ->where('idempotency_key_hash', $keyHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof ProposedOperation) {
                    if (! is_string($existing->request_hash) || ! hash_equals($existing->request_hash, $requestHash)) {
                        throw new ConflictHttpException('The idempotency key was already used for a different proposal.');
                    }

                    return $existing;
                }

                $canonicalInput = $this->resolveNameLocators($operation, $input, $context, $unit);
                if ($operation === 'category.create') {
                    $canonicalInput['is_active'] ??= true;
                }
                $canonicalInput = $this->canonicalize($canonicalInput);
                $inputHash = hash('sha256', json_encode($canonicalInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                return ProposedOperation::query()->create([
                    'id' => (string) Str::uuid(),
                    'credential_id' => $sourceColumn === 'credential_id' ? $sourceId : null,
                    'oauth_grant_id' => $sourceColumn === 'oauth_grant_id' ? $sourceId : null,
                    'source' => $sourceType,
                    'actor_id' => $context->user->getKey(),
                    'tenant_id' => $context->tenant->getKey(),
                    'unit_id' => $context->unit->getKey(),
                    'operation_key' => $operation,
                    'input' => $canonicalInput,
                    'input_hash' => $inputHash,
                    'request_hash' => $requestHash,
                    'idempotency_key_hash' => $keyHash,
                    'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
                    'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $existing = ProposedOperation::query()
                ->where('source', $sourceType)
                ->where($sourceColumn, $sourceId)
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if (! $existing instanceof ProposedOperation) {
                throw $exception;
            }
            if (! is_string($existing->request_hash) || ! hash_equals($existing->request_hash, $requestHash)) {
                throw new ConflictHttpException('The idempotency key was already used for a different proposal.', $exception);
            }

            return $existing;
        }
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function resolveNameLocators(string $operation, array $input, TenantContext $context, Unit $unit): array
    {
        $providedFields = match ($operation) {
            'category.update' => array_diff(array_keys($input), ['category_name']),
            'professional.update' => array_values(array_map(static fn (string $field): string => $field === 'service_names' ? 'service_ids' : $field, array_diff(array_keys($input), ['professional_name']))),
            'service.update' => array_values(array_map(static fn (string $field): string => match ($field) {
                'category_name' => 'category_id',
                'professional_names' => 'professional_ids',
                default => $field,
            }, array_diff(array_keys($input), ['service_name']))),
            default => [],
        };

        if ($operation === 'category.update') {
            $category = $this->resolveNamedRecord(Category::class, $input['category_name'], 'input.category_name', $context, $unit);
            if (! $category instanceof Category) {
                throw new \LogicException('The category locator resolved to an unexpected model.');
            }
            Gate::authorize('update', $category);
            $input['category_id'] = (string) $category->getKey();
            $input['expected_version'] = $category->lock_version;
            unset($input['category_name']);
            $input['_provided_fields'] = $providedFields;
        }

        if ($operation === 'service.update') {
            $service = $this->resolveNamedRecord(Service::class, $input['service_name'], 'input.service_name', $context, $unit);
            if (! $service instanceof Service) {
                throw new \LogicException('The service locator resolved to an unexpected model.');
            }
            Gate::authorize('update', $service);
            $input['service_id'] = (string) $service->getKey();
            $input['expected_version'] = $service->lock_version;
            unset($input['service_name']);
            if (array_key_exists('category_name', $input)) {
                $input['category_id'] = $input['category_name'] === null
                    ? null
                    : (string) $this->resolveServiceCategory($input['category_name'], $context, $unit)->getKey();
                unset($input['category_name']);
            }
            if (array_key_exists('professional_names', $input)) {
                $input['professional_ids'] = $this->resolveNamedIds(Professional::class, $input['professional_names'], 'input.professional_names', $context, $unit);
                unset($input['professional_names']);
            }
            $input['_snapshot'] = [
                'category_id' => $service->category_id === null ? null : (string) $service->category_id,
                'professional_ids' => $this->serviceProfessionalIds($service, $context),
            ];
            $input['_provided_fields'] = $providedFields;
        }

        if (in_array($operation, ['professional.create', 'professional.update'], true)
            && array_key_exists('service_names', $input)) {
            $input['service_ids'] = $this->resolveNamedIds(Service::class, $input['service_names'], 'input.service_names', $context, $unit);
            unset($input['service_names']);
        }

        if ($operation === 'professional.update') {
            $professional = $this->resolveNamedRecord(Professional::class, $input['professional_name'], 'input.professional_name', $context, $unit);
            if (! $professional instanceof Professional) {
                throw new \LogicException('The professional locator resolved to an unexpected model.');
            }
            Gate::authorize('update', $professional);
            $currentServiceIds = $this->professionalServiceIds($professional, $context);
            $input['professional_id'] = (string) $professional->getKey();
            $input['expected_version'] = $professional->lock_version;
            $input['name'] = $input['name'] ?? $professional->name;
            $input['status'] = $input['status'] ?? $professional->status;
            $input['service_ids'] = $input['service_ids'] ?? $currentServiceIds;
            unset($input['professional_name']);
            $input['_snapshot'] = [
                'service_ids' => $currentServiceIds,
            ];
            $input['_provided_fields'] = $providedFields;
        }

        if ($operation === 'unit.update') {
            $currentUnit = Unit::query()
                ->whereKey($unit->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->select(['id', 'tenant_id', 'name', 'timezone', 'online_booking_enabled', 'appointment_sales_automation_enabled', 'lock_version'])
                ->lockForUpdate()
                ->firstOrFail();
            $input = [
                'name' => $input['name'] ?? $currentUnit->name,
                'timezone' => array_key_exists('timezone', $input) ? $input['timezone'] : $currentUnit->timezone,
                'online_booking_enabled' => $input['online_booking_enabled'] ?? $currentUnit->online_booking_enabled,
                'appointment_sales_automation_enabled' => $input['appointment_sales_automation_enabled'] ?? $currentUnit->appointment_sales_automation_enabled,
                'expected_version' => $currentUnit->lock_version,
                '_provided_fields' => array_keys($input),
            ];
        }

        if (in_array($operation, ['service.create', 'service.update'], true)) {
            if ($operation === 'service.create' && array_key_exists('category_name', $input)) {
                $input['category_id'] = $input['category_name'] === null
                    ? null
                    : (string) $this->resolveServiceCategory($input['category_name'], $context, $unit)->getKey();
                unset($input['category_name']);
            }
            if ($operation === 'service.create' && array_key_exists('professional_names', $input)) {
                $input['professional_ids'] = $this->resolveNamedIds(Professional::class, $input['professional_names'], 'input.professional_names', $context, $unit);
                unset($input['professional_names']);
            }
        }

        return $input;
    }

    private function resolveNamedRecord(string $model, mixed $name, string $field, TenantContext $context, Unit $unit): Category|Service|Professional
    {
        $matches = $this->namedRecords($model, $name, $context, $unit);
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                $field => ['The exact name must match exactly one item in the bound unit.'],
            ]);
        }

        $record = $matches->first();
        if (! $record instanceof Category && ! $record instanceof Service && ! $record instanceof Professional) {
            throw new \LogicException('The name locator resolved to an unexpected model.');
        }

        return $record;
    }

    private function resolveServiceCategory(mixed $name, TenantContext $context, Unit $unit): Category
    {
        $matches = Category::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('type', 'service')
            ->select(['id', 'name', 'type'])
            ->where('name', $name)
            ->orderBy('id')
            ->limit(2)
            ->lockForUpdate()
            ->get();
        $category = $matches->first();
        if ($matches->count() !== 1 || ! $category instanceof Category) {
            throw ValidationException::withMessages([
                'input.category_name' => ['The exact name must match exactly one service category in the bound unit.'],
            ]);
        }

        return $category;
    }

    /** @return list<string> */
    private function resolveNamedIds(string $model, mixed $names, string $field, TenantContext $context, Unit $unit): array
    {
        if (! is_array($names)) {
            throw ValidationException::withMessages([$field => ['The name list is invalid.']]);
        }

        $ids = [];
        foreach ($names as $name) {
            if (! is_string($name)) {
                throw ValidationException::withMessages([$field => ['Each exact name must match exactly one item in the bound unit.']]);
            }

            $ids[] = (string) $this->resolveNamedRecord($model, $name, $field, $context, $unit)->getKey();
        }

        return $ids;
    }

    /** @return EloquentCollection<int, Category>|EloquentCollection<int, Service>|EloquentCollection<int, Professional> */
    private function namedRecords(string $model, mixed $name, TenantContext $context, Unit $unit): EloquentCollection
    {
        if (! is_string($name)) {
            return new EloquentCollection;
        }

        return match ($model) {
            Category::class => Category::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->select(['id', 'name', 'tenant_id', 'unit_id', 'lock_version'])
                ->where('name', $name)->orderBy('id')->limit(2)->lockForUpdate()->get(),
            Service::class => Service::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->select(['id', 'name', 'tenant_id', 'unit_id', 'lock_version', 'category_id'])
                ->where('name', $name)->orderBy('id')->limit(2)->lockForUpdate()->get(),
            Professional::class => Professional::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $unit->getKey())
                ->select(['id', 'name', 'tenant_id', 'unit_id', 'lock_version'])
                ->where('name', $name)->orderBy('id')->limit(2)->lockForUpdate()->get(),
            default => throw new \LogicException('The name locator model is not supported.'),
        };
    }

    /** @return list<string> */
    private function serviceProfessionalIds(Service $service, TenantContext $context): array
    {
        $professionalIds = $service->professionals()
            ->where('professionals.tenant_id', $context->tenant->getKey())
            ->where('professionals.unit_id', $context->unit?->getKey())
            ->orderBy('professionals.id')
            ->pluck('professionals.id')
            ->all();

        $ids = [];
        foreach ($professionalIds as $id) {
            $ids[] = (string) $id;
        }

        return $ids;
    }

    /** @return list<string> */
    private function professionalServiceIds(Professional $professional, TenantContext $context): array
    {
        $serviceIds = $professional->services()
            ->where('services.tenant_id', $context->tenant->getKey())
            ->where('services.unit_id', $context->unit?->getKey())
            ->orderBy('services.id')
            ->pluck('services.id')
            ->all();

        $ids = [];
        foreach ($serviceIds as $id) {
            $ids[] = (string) $id;
        }

        return $ids;
    }

    private function resolvedSnapshotIsCurrent(ProposedOperation $proposal, TenantContext $context): bool
    {
        $input = $proposal->input;
        $snapshot = $input['_snapshot'] ?? [];

        if ($proposal->operation_key === 'category.update') {
            $category = Category::query()
                ->whereKey($input['category_id'] ?? '')
                ->where('tenant_id', $proposal->tenant_id)
                ->where('unit_id', $proposal->unit_id)
                ->lockForUpdate()
                ->first();

            return $category instanceof Category
                && $category->lock_version === (int) ($input['expected_version'] ?? -1);
        }

        if ($proposal->operation_key === 'service.update') {
            $service = Service::query()
                ->whereKey($input['service_id'] ?? '')
                ->where('tenant_id', $proposal->tenant_id)
                ->where('unit_id', $proposal->unit_id)
                ->lockForUpdate()
                ->first();
            if (! $service instanceof Service || $service->lock_version !== (int) ($input['expected_version'] ?? -1)) {
                return false;
            }

            $currentProfessionalIds = $this->serviceProfessionalIds($service, $context);
            $expectedProfessionalIds = $snapshot['professional_ids'] ?? null;
            $categoryIdMatches = ($service->category_id === null ? null : (string) $service->category_id)
                === ($snapshot['category_id'] ?? null);

            return is_array($expectedProfessionalIds)
                && $currentProfessionalIds === $expectedProfessionalIds
                && $categoryIdMatches;
        }

        if ($proposal->operation_key === 'professional.update') {
            $professional = Professional::query()
                ->whereKey($input['professional_id'] ?? '')
                ->where('tenant_id', $proposal->tenant_id)
                ->where('unit_id', $proposal->unit_id)
                ->lockForUpdate()
                ->first();
            if (! $professional instanceof Professional || $professional->lock_version !== (int) ($input['expected_version'] ?? -1)) {
                return false;
            }

            $currentServiceIds = $this->professionalServiceIds($professional, $context);
            $expectedServiceIds = $snapshot['service_ids'] ?? null;

            return is_array($expectedServiceIds) && $currentServiceIds === $expectedServiceIds;
        }

        return true;
    }

    public function confirm(
        ProposedOperation $proposal,
        User $actor,
        TenantContext $context,
        CreateService $createService,
        CreateCategory $createCategory,
        UpdateCategory $updateCategory,
        UpdateService $updateService,
        UpdateUnitSettings $updateUnitSettings,
        CreateAvailabilityRule $createAvailabilityRule,
        UpdateAvailabilityRule $updateAvailabilityRule,
        DeleteAvailabilityRule $deleteAvailabilityRule,
        CreateScheduleBlock $createScheduleBlock,
        UpdateScheduleBlock $updateScheduleBlock,
        DeleteScheduleBlock $deleteScheduleBlock,
        UpdateOnlineBookingSettings $updateOnlineBookingSettings,
        SaveOnlineBookingDraft $saveOnlineBookingDraft,
        PublishOnlineBookingSite $publishOnlineBookingSite,
        UnpublishOnlineBookingSite $unpublishOnlineBookingSite,
        ?ExecuteProfessionalConfigurationOperation $professionalConfiguration = null,
    ): ProposedOperation {
        /** @var array{expired: bool, failure: Throwable|null, proposal: ProposedOperation} $outcome */
        $outcome = DB::transaction(function () use ($proposal, $actor, $context, $createService, $createCategory, $updateCategory, $updateService, $updateUnitSettings, $createAvailabilityRule, $updateAvailabilityRule, $deleteAvailabilityRule, $createScheduleBlock, $updateScheduleBlock, $deleteScheduleBlock, $updateOnlineBookingSettings, $saveOnlineBookingDraft, $publishOnlineBookingSite, $unpublishOnlineBookingSite, $professionalConfiguration): array {
            $locked = ProposedOperation::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
            $freshContext = $this->revalidateContext($context);
            $this->assertProposalContext($locked, $actor, $freshContext);

            if ($locked->status !== ProposedOperation::STATUS_PENDING_CONFIRMATION) {
                throw new ConflictHttpException('This proposal has already been resolved.');
            }

            if ($locked->expires_at->isPast()) {
                $locked->markExpired();

                return ['expired' => true, 'failure' => null, 'proposal' => $locked];
            }

            $actualHash = $this->inputHash($locked->input);
            if (! hash_equals($locked->input_hash, $actualHash)) {
                $failure = new ConflictHttpException('The proposal payload failed its integrity check.');
                $locked->markFailed();

                return ['expired' => false, 'failure' => $failure, 'proposal' => $locked];
            }

            $reviewDetails = in_array($locked->operation_key, ['professional.create', 'professional.update', 'service.create', 'service.update'], true)
                ? $this->reviewDetails($locked, $freshContext)
                : ['professionals' => [], 'professionalAssignmentsValid' => true, 'serviceAssignmentsValid' => true];
            if (! $reviewDetails['professionalAssignmentsValid'] || ! $reviewDetails['serviceAssignmentsValid']) {
                $failure = new ConflictHttpException('One or more selected catalog links are no longer available in this unit. Review and create a new proposal.');
                $locked->markNeedsRefresh();

                return ['expired' => false, 'failure' => $failure, 'proposal' => $locked];
            }
            if (! $this->resolvedSnapshotIsCurrent($locked, $freshContext)) {
                $locked->markNeedsRefresh();

                return [
                    'expired' => false,
                    'failure' => new ConflictHttpException('The catalog item or its relationships changed after this proposal was created. Review the current item and create a new proposal.'),
                    'proposal' => $locked,
                ];
            }

            $locked->markExecuting();
            try {
                $resource = match ($locked->operation_key) {
                    'professional.create', 'professional.update' => $professionalConfiguration?->handle($actor, $freshContext, $locked->operation_key, $locked->input)
                        ?? throw new ConflictHttpException('This operation is not supported.'),
                    'service.create' => $createService->handle($actor, $freshContext, $locked->input),
                    'service.update' => $this->updateService($locked, $actor, $freshContext, $updateService),
                    'category.create' => $createCategory->handle($actor, $freshContext, $locked->input),
                    'category.update' => $this->updateCategory($locked, $actor, $freshContext, $updateCategory),
                    'unit.update' => $this->updateUnitSettings($locked, $actor, $freshContext, $updateUnitSettings),
                    'availability_rule.create' => $createAvailabilityRule->handle($actor, $freshContext, $locked->input),
                    'availability_rule.update' => $this->updateAvailabilityRule($locked, $actor, $freshContext, $updateAvailabilityRule),
                    'availability_rule.delete' => $this->deleteAvailabilityRule($locked, $actor, $freshContext, $deleteAvailabilityRule),
                    'schedule_block.create' => $createScheduleBlock->handle($actor, $freshContext, [
                        ...$locked->input,
                        'professional_id' => $locked->input['professional_id'] ?? null,
                    ]),
                    'schedule_block.update' => $this->updateScheduleBlock($locked, $actor, $freshContext, $updateScheduleBlock),
                    'schedule_block.delete' => $this->deleteScheduleBlock($locked, $actor, $freshContext, $deleteScheduleBlock),
                    'booking.settings.update' => $this->updateBookingSettings($locked, $actor, $freshContext, $updateOnlineBookingSettings),
                    'booking.draft.update' => $saveOnlineBookingDraft->handle($actor, $freshContext, $locked->input['content'], (int) $locked->input['expected_revision']),
                    'booking.publish' => $publishOnlineBookingSite->handle($actor, $freshContext, (int) $locked->input['expected_revision']),
                    'booking.unpublish' => $this->unpublishBooking($locked, $actor, $freshContext, $unpublishOnlineBookingSite),
                    default => throw new ConflictHttpException('This operation is not supported.'),
                };
            } catch (AuthorizationException) {
                $locked->markNeedsRefresh();

                return [
                    'expired' => false,
                    'failure' => new ConflictHttpException('The proposal is no longer authorized in the current context. Refresh and create a new proposal.'),
                    'proposal' => $locked,
                ];
            } catch (ConflictHttpException $exception) {
                if (! $this->requiresRefreshOnConflict($locked->operation_key)) {
                    $locked->markFailed();

                    return ['expired' => false, 'failure' => $exception, 'proposal' => $locked];
                }

                $locked->markNeedsRefresh();

                return [
                    'expired' => false,
                    'failure' => new ConflictHttpException(
                        match ($locked->operation_key) {
                            'category.update' => 'The category changed after this proposal was created. Review the current category and create a new proposal.',
                            'service.update' => 'The service or its assignments changed after this proposal was created. Review the current service and create a new proposal.',
                            'professional.update' => 'The professional or its service assignments changed after this proposal was created. Review the current professional and create a new proposal.',
                            'availability_rule.update', 'availability_rule.delete' => 'The availability rule changed after this proposal was created. Review the current rule and create a new proposal.',
                            'schedule_block.update', 'schedule_block.delete' => 'The schedule block changed after this proposal was created. Review the current block and create a new proposal.',
                            'booking.settings.update' => 'The booking settings changed after this proposal was created. Review the current settings and create a new proposal.',
                            'booking.draft.update', 'booking.publish' => 'The booking draft changed after this proposal was created. Review the current draft and create a new proposal.',
                            'booking.unpublish' => 'The booking site changed after this proposal was created. Review the current site and create a new proposal.',
                            default => 'The unit settings changed after this proposal was created. Review the current settings and create a new proposal.',
                        },
                        $exception,
                    ),
                    'proposal' => $locked,
                ];
            } catch (ModelNotFoundException $exception) {
                if (! $this->requiresRefreshOnConflict($locked->operation_key)) {
                    $locked->markFailed();

                    return ['expired' => false, 'failure' => $exception, 'proposal' => $locked];
                }

                $locked->markNeedsRefresh();

                return [
                    'expired' => false,
                    'failure' => new ConflictHttpException(
                        $this->refreshFailureMessage($locked->operation_key),
                        $exception,
                    ),
                    'proposal' => $locked,
                ];
            } catch (Throwable $exception) {
                $locked->markFailed();

                return ['expired' => false, 'failure' => $exception, 'proposal' => $locked];
            }
            $locked->markSucceeded([
                'resource_id' => (string) $resource->getKey(),
                'resource_type' => match (true) {
                    str_starts_with($locked->operation_key, 'category.') => 'category',
                    str_starts_with($locked->operation_key, 'service.') => 'service',
                    str_starts_with($locked->operation_key, 'professional.') => 'professional',
                    str_starts_with($locked->operation_key, 'availability_rule.') => 'availability_rule',
                    str_starts_with($locked->operation_key, 'schedule_block.') => 'schedule_block',
                    str_starts_with($locked->operation_key, 'booking.') => 'online_booking',
                    $locked->operation_key === 'unit.update' => 'unit',
                    default => 'unknown',
                },
            ]);

            return ['expired' => false, 'failure' => null, 'proposal' => $locked];
        }, 3);

        if ($outcome['expired']) {
            throw new ConflictHttpException('This proposal has expired.');
        }
        if ($outcome['failure'] instanceof Throwable) {
            throw $outcome['failure'];
        }

        return $outcome['proposal'];
    }

    public function reject(ProposedOperation $proposal, User $actor, TenantContext $context): ProposedOperation
    {
        return DB::transaction(function () use ($proposal, $actor, $context): ProposedOperation {
            $locked = ProposedOperation::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
            $freshContext = $this->revalidateContext($context);
            $this->assertProposalContext($locked, $actor, $freshContext);

            if ($locked->status !== ProposedOperation::STATUS_PENDING_CONFIRMATION) {
                throw new ConflictHttpException('This proposal has already been resolved.');
            }

            if ($locked->expires_at->isPast()) {
                $locked->markExpired();

                return $locked;
            }

            $locked->markRejected();

            return $locked;
        }, 3);
    }

    public function show(ProposedOperation $proposal, User $actor, TenantContext $context): ProposedOperation
    {
        return DB::transaction(function () use ($proposal, $actor, $context): ProposedOperation {
            $locked = ProposedOperation::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
            $freshContext = $this->revalidateContext($context);
            $this->assertProposalContext($locked, $actor, $freshContext);

            if ($locked->status === ProposedOperation::STATUS_PENDING_CONFIRMATION && $locked->expires_at->isPast()) {
                $locked->markExpired();
            }

            return $locked;
        }, 3);
    }

    /** @return array<string, mixed> */
    public function summary(ProposedOperation $proposal, TenantContext $context): array
    {
        if ($proposal->operation_key === 'category.create') {
            return [
                'name' => $proposal->input['name'],
                'type' => $proposal->input['type'],
                'description' => $proposal->input['description'] ?? null,
                'is_active' => $proposal->input['is_active'] ?? true,
            ];
        }

        if ($proposal->operation_key === 'category.update') {
            return [
                'category_id' => $proposal->input['category_id'],
                'expected_version' => $proposal->input['expected_version'],
                'changed_fields' => array_values(array_filter(
                    ['name', 'type', 'description', 'is_active'],
                    fn (string $field): bool => in_array($field, $proposal->input['_provided_fields'] ?? [], true),
                )),
            ];
        }

        if ($proposal->operation_key === 'professional.create') {
            return [
                'name' => $proposal->input['name'],
                'status' => $proposal->input['status'],
                ...(array_key_exists('service_ids', $proposal->input) ? ['service_ids' => $proposal->input['service_ids']] : []),
                ...(array_key_exists('service_ids', $proposal->input) ? ['service_count' => count($proposal->input['service_ids'])] : []),
            ];
        }

        if ($proposal->operation_key === 'professional.update') {
            $changedFields = array_values(array_map(static fn (string $field): string => $field === 'service_ids' ? 'service_names' : $field, $proposal->input['_provided_fields'] ?? []));
            $professional = Professional::query()
                ->whereKey($proposal->input['professional_id'] ?? '')
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['id', 'name', 'status']);
            $details = $this->reviewDetails($proposal, $context);
            $currentServiceNames = array_map(static fn (array $service): ?string => $service['name'], $details['currentServices']);
            $proposedServiceNames = array_map(static fn (array $service): ?string => $service['name'], $details['proposedServices']);

            return [
                'name' => $proposal->input['name'],
                'status' => $proposal->input['status'],
                'current' => $professional instanceof Professional ? [
                    'name' => $professional->name,
                    'status' => $professional->status,
                    'service_names' => $currentServiceNames,
                ] : null,
                'proposed' => [
                    'name' => $proposal->input['name'],
                    'status' => $proposal->input['status'],
                    'service_names' => $proposedServiceNames,
                ],
                'changed_fields' => $changedFields,
            ];
        }

        if ($proposal->operation_key === 'service.update') {
            return [
                'service_id' => $proposal->input['service_id'],
                'expected_version' => $proposal->input['expected_version'],
                'changed_fields' => array_values(array_filter(
                    ['name', 'description', 'duration_minutes', 'price_cents', 'status', 'category_id', 'professional_ids'],
                    fn (string $field): bool => in_array($field, $proposal->input['_provided_fields'] ?? [], true),
                )),
            ];
        }

        if ($proposal->operation_key === 'unit.update') {
            return [
                'name' => $proposal->input['name'],
                'expected_version' => $proposal->input['expected_version'],
                'timezone' => $proposal->input['timezone'],
                'address' => $proposal->input['address'] ?? null,
                'online_booking_enabled' => $proposal->input['online_booking_enabled'],
                'appointment_sales_automation_enabled' => $proposal->input['appointment_sales_automation_enabled'],
            ];
        }

        if (str_starts_with($proposal->operation_key, 'availability_rule.')) {
            return [
                'name' => 'Regra de disponibilidade',
                'availability_rule_id' => $proposal->input['availability_rule_id'] ?? null,
                'expected_version' => $proposal->input['expected_version'] ?? null,
                'professional_id' => $proposal->input['professional_id'] ?? null,
                'weekday' => $proposal->input['weekday'] ?? null,
                'starts_at' => $proposal->input['starts_at'] ?? null,
                'ends_at' => $proposal->input['ends_at'] ?? null,
                'timezone' => $proposal->input['timezone'] ?? null,
                'status' => $proposal->input['status'] ?? 'active',
            ];
        }

        if (str_starts_with($proposal->operation_key, 'schedule_block.')) {
            return [
                'name' => 'Bloqueio de agenda',
                'schedule_block_id' => $proposal->input['schedule_block_id'] ?? null,
                'expected_version' => $proposal->input['expected_version'] ?? null,
                'professional_id' => $proposal->input['professional_id'] ?? null,
                'starts_at' => $proposal->input['starts_at'] ?? null,
                'ends_at' => $proposal->input['ends_at'] ?? null,
                'timezone' => $proposal->input['timezone'] ?? null,
                'reason' => $proposal->input['reason'] ?? null,
                'status' => $proposal->input['status'] ?? 'active',
            ];
        }

        if ($proposal->operation_key === 'booking.settings.update') {
            return [
                'name' => 'Configurações de agendamento online',
                ...$proposal->input,
            ];
        }

        if ($proposal->operation_key === 'booking.draft.update') {
            return [
                'name' => 'Rascunho do agendamento online',
                'expected_revision' => $proposal->input['expected_revision'],
                'content' => $proposal->input['content'],
            ];
        }

        if ($proposal->operation_key === 'booking.publish') {
            return [
                'name' => 'Publicação do agendamento online',
                'expected_revision' => $proposal->input['expected_revision'],
            ];
        }

        if ($proposal->operation_key === 'booking.unpublish') {
            return [
                'name' => 'Retirada do agendamento online',
                'expected_version' => $proposal->input['expected_version'],
            ];
        }

        return [
            'name' => $proposal->input['name'],
            'description' => $proposal->input['description'] ?? null,
            'duration_minutes' => $proposal->input['duration_minutes'],
            'price_cents' => $proposal->input['price_cents'],
            'status' => $proposal->input['status'] ?? 'active',
            'professional_count' => count($proposal->input['professional_ids'] ?? []),
        ];
    }

    /**
     * Return the minimal proposal representation allowed across REST and MCP.
     *
     * Proposal input is an administrator command and can contain arbitrary
     * values, resource identifiers, relationship identifiers, or structured
     * booking configuration. Those values remain available to the scoped
     * authenticated Inertia review only.
     *
     * @return array{changed_fields: list<string>}
     */
    public function externalSummary(ProposedOperation $proposal): array
    {
        if ($proposal->operation_key === 'professional.update') {
            $providedFields = array_values(array_filter(
                $proposal->input['_provided_fields'] ?? [],
                static fn (mixed $field): bool => is_string($field),
            ));

            return ['changed_fields' => array_map(static fn (string $field): string => $field === 'service_ids' ? 'service_names' : $field, $providedFields)];
        }

        $fieldOrder = match (true) {
            in_array($proposal->operation_key, ['category.create', 'category.update'], true) => ['name', 'type', 'is_active'],
            in_array($proposal->operation_key, ['professional.create', 'professional.update'], true) => ['name', 'status', 'service_ids'],
            in_array($proposal->operation_key, ['service.create', 'service.update'], true) => ['name', 'duration_minutes', 'price_cents', 'status', 'category_id', 'professional_ids'],
            $proposal->operation_key === 'unit.update' => $proposal->input['_provided_fields'] ?? [],
            default => [],
        };

        $changedFields = array_map(static fn (string $field): string => match ($field) {
            'service_ids' => 'service_names',
            'professional_ids' => 'professional_names',
            'category_id' => 'category_name',
            default => $field,
        }, array_intersect($fieldOrder, array_keys($proposal->input)));

        return ['changed_fields' => array_values($changedFields)];
    }

    /** @return array{kind: string, current: array<string, mixed>|null, proposed: array<string, mixed>, expectedVersionMatches: bool} */
    public function operationReview(ProposedOperation $proposal, TenantContext $context): array
    {
        $operation = $proposal->operation_key;
        $summary = $this->summary($proposal, $context);

        if (in_array($operation, ['category.create', 'professional.create', 'service.create', 'availability_rule.create', 'schedule_block.create'], true)) {
            return ['kind' => $operation, 'current' => null, 'proposed' => $summary, 'expectedVersionMatches' => true];
        }

        if (in_array($operation, ['category.update', 'professional.update', 'service.update', 'unit.update'], true)) {
            if ($operation === 'category.update') {
                $category = Category::query()
                    ->whereKey($proposal->input['category_id'] ?? '')
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $context->unit?->getKey())
                    ->first(['lock_version']);

                return [
                    'kind' => $operation,
                    'current' => null,
                    'proposed' => $summary,
                    'expectedVersionMatches' => $category instanceof Category
                        && $category->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
                ];
            }

            if ($operation === 'service.update') {
                $service = Service::query()
                    ->whereKey($proposal->input['service_id'] ?? '')
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $context->unit?->getKey())
                    ->first(['lock_version']);

                return [
                    'kind' => $operation,
                    'current' => null,
                    'proposed' => $summary,
                    'expectedVersionMatches' => $service instanceof Service
                        && $service->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
                ];
            }

            if ($operation === 'professional.update') {
                $professional = Professional::query()
                    ->whereKey($proposal->input['professional_id'] ?? '')
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $context->unit?->getKey())
                    ->first(['id', 'name', 'status', 'lock_version']);

                return [
                    'kind' => $operation,
                    'current' => $summary['current'] ?? null,
                    'proposed' => [...$summary['proposed'], 'changed_fields' => $summary['changed_fields']],
                    'expectedVersionMatches' => $professional instanceof Professional
                        && $professional->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
                ];
            }

            return ['kind' => $operation, 'current' => null, 'proposed' => $summary, 'expectedVersionMatches' => true];
        }

        if (str_starts_with($operation, 'availability_rule.')) {
            $rule = AvailabilityRule::query()
                ->whereKey($proposal->input['availability_rule_id'] ?? '')
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['id', 'professional_id', 'weekday', 'starts_at', 'ends_at', 'timezone', 'status', 'lock_version']);

            return [
                'kind' => $operation,
                'current' => $rule?->toArray(),
                'proposed' => $summary,
                'expectedVersionMatches' => $rule instanceof AvailabilityRule
                    && $rule->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
            ];
        }

        if (str_starts_with($operation, 'schedule_block.')) {
            $block = ScheduleBlock::query()
                ->whereKey($proposal->input['schedule_block_id'] ?? '')
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['id', 'professional_id', 'starts_at', 'ends_at', 'timezone', 'reason', 'status', 'lock_version']);

            return [
                'kind' => $operation,
                'current' => $block?->toArray(),
                'proposed' => $summary,
                'expectedVersionMatches' => $block instanceof ScheduleBlock
                    && $block->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
            ];
        }

        if ($operation === 'booking.settings.update') {
            $unit = Unit::query()
                ->whereKey($context->unit?->getKey())
                ->where('tenant_id', $context->tenant->getKey())
                ->first(['name', 'slug', 'online_booking_enabled', 'lock_version']);
            $setting = OnlineBookingSetting::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first();
            $site = OnlineBookingSite::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['template_key', 'public_slug', 'lock_version', 'status']);

            return [
                'kind' => $operation,
                'current' => [
                    'unit' => $unit?->toArray(),
                    'settings' => $setting?->makeHidden(['cover_image_path', 'logo_image_path'])?->toArray(),
                    'site' => $site?->toArray(),
                ],
                'proposed' => $summary,
                'expectedVersionMatches' => $unit instanceof Unit
                    && $unit->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
            ];
        }

        if ($operation === 'booking.draft.update') {
            $site = OnlineBookingSite::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first();
            $draft = $site?->draft;

            return [
                'kind' => $operation,
                'current' => $draft?->only(['revision', 'content']),
                'proposed' => $summary,
                'expectedVersionMatches' => $draft === null
                    ? (int) ($proposal->input['expected_revision'] ?? -1) === 0
                    : $draft->revision === (int) ($proposal->input['expected_revision'] ?? -1),
            ];
        }

        if ($operation === 'booking.publish') {
            $site = OnlineBookingSite::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['status', 'draft_revision', 'lock_version']);

            return [
                'kind' => $operation,
                'current' => $site?->toArray(),
                'proposed' => $summary,
                'expectedVersionMatches' => $site instanceof OnlineBookingSite
                    && $site->draft_revision === (int) ($proposal->input['expected_revision'] ?? -1),
            ];
        }

        if ($operation === 'booking.unpublish') {
            $site = OnlineBookingSite::query()
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->first(['status', 'draft_revision', 'lock_version']);

            return [
                'kind' => $operation,
                'current' => $site?->toArray(),
                'proposed' => $summary,
                'expectedVersionMatches' => $site instanceof OnlineBookingSite
                    && $site->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
            ];
        }

        return ['kind' => $operation, 'current' => null, 'proposed' => $summary, 'expectedVersionMatches' => true];
    }

    /** @return array{name: string, type: string, description: string|null, is_active: bool, lock_version: int}|null */
    public function categoryForReview(ProposedOperation $proposal, TenantContext $context): ?array
    {
        if ($proposal->operation_key !== 'category.update'
            || $proposal->tenant_id !== (string) $context->tenant->getKey()
            || $proposal->unit_id !== (string) $context->unit?->getKey()) {
            return null;
        }

        $category = Category::query()
            ->whereKey($proposal->input['category_id'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->first(['name', 'type', 'description', 'is_active', 'lock_version']);

        if (! $category instanceof Category) {
            return null;
        }

        return [
            'name' => $category->name,
            'type' => $category->type,
            'description' => $category->description,
            'is_active' => $category->is_active,
            'lock_version' => $category->lock_version,
        ];
    }

    /** @return array{name: string, type: string, description: string|null, is_active: bool}|null */
    public function proposedCategoryForReview(ProposedOperation $proposal, TenantContext $context): ?array
    {
        $current = $this->categoryForReview($proposal, $context);
        if ($current === null) {
            return null;
        }

        return [
            'name' => array_key_exists('name', $proposal->input) ? $proposal->input['name'] : $current['name'],
            'type' => array_key_exists('type', $proposal->input) ? $proposal->input['type'] : $current['type'],
            'description' => array_key_exists('description', $proposal->input) ? $proposal->input['description'] : $current['description'],
            'is_active' => array_key_exists('is_active', $proposal->input) ? $proposal->input['is_active'] : $current['is_active'],
        ];
    }

    /** @return array{name: string, description: string|null, duration_minutes: int, price_cents: int, status: string, lock_version: int, category_id: string|null, category_name: string|null, professionals: list<array{id: string, name: string|null}>}|null */
    public function serviceForReview(ProposedOperation $proposal, TenantContext $context): ?array
    {
        if ($proposal->operation_key !== 'service.update'
            || $proposal->tenant_id !== (string) $context->tenant->getKey()
            || $proposal->unit_id !== (string) $context->unit?->getKey()) {
            return null;
        }

        $service = Service::query()
            ->whereKey($proposal->input['service_id'])
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->with(['category:id,name'])
            ->first(['id', 'category_id', 'name', 'description', 'duration_minutes', 'price_cents', 'status', 'lock_version']);

        if (! $service instanceof Service) {
            return null;
        }

        return [
            'name' => $service->name,
            'description' => $service->description,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => $service->price_cents,
            'status' => $service->status,
            'lock_version' => $service->lock_version,
            'category_id' => $service->category === null ? null : (string) $service->category->getKey(),
            'category_name' => $service->category?->name,
            'professionals' => array_values($service->professionals()
                ->where('professionals.tenant_id', $context->tenant->getKey())
                ->where('professionals.unit_id', $context->unit?->getKey())
                ->orderBy('professionals.name')
                ->get(['professionals.id', 'professionals.name'])
                ->map(fn (Professional $professional): array => ['id' => (string) $professional->getKey(), 'name' => $professional->name])
                ->all()),
        ];
    }

    /** @return array{name: string, description: string|null, duration_minutes: int, price_cents: int, status: string}|null */
    public function proposedServiceForReview(ProposedOperation $proposal, TenantContext $context): ?array
    {
        $current = $this->serviceForReview($proposal, $context);
        if ($current === null) {
            return null;
        }

        return [
            'name' => array_key_exists('name', $proposal->input) ? $proposal->input['name'] : $current['name'],
            'description' => array_key_exists('description', $proposal->input) ? $proposal->input['description'] : $current['description'],
            'duration_minutes' => $proposal->input['duration_minutes'] ?? $current['duration_minutes'],
            'price_cents' => $proposal->input['price_cents'] ?? $current['price_cents'],
            'status' => $proposal->input['status'] ?? $current['status'],
        ];
    }

    /** @return array{unit: array{name: string, timezone: string|null, address: array<string, string>|null, online_booking_enabled: bool, appointment_sales_automation_enabled: bool, lock_version: int}|null, expectedVersionMatches: bool} */
    public function unitForReview(ProposedOperation $proposal, TenantContext $context): array
    {
        if ($proposal->operation_key !== 'unit.update'
            || $proposal->tenant_id !== (string) $context->tenant->getKey()
            || $proposal->unit_id !== (string) $context->unit?->getKey()) {
            return ['unit' => null, 'expectedVersionMatches' => false];
        }

        $unit = Unit::query()
            ->whereKey($context->unit?->getKey())
            ->where('tenant_id', $context->tenant->getKey())
            ->first(['name', 'timezone', 'address', 'online_booking_enabled', 'appointment_sales_automation_enabled', 'lock_version']);

        if (! $unit instanceof Unit) {
            return ['unit' => null, 'expectedVersionMatches' => false];
        }

        return [
            'unit' => [
                'name' => $unit->name,
                'timezone' => $unit->timezone,
                'address' => $unit->address,
                'online_booking_enabled' => $unit->online_booking_enabled,
                'appointment_sales_automation_enabled' => $unit->appointment_sales_automation_enabled,
                'lock_version' => $unit->lock_version,
            ],
            'expectedVersionMatches' => $unit->lock_version === (int) ($proposal->input['expected_version'] ?? -1),
        ];
    }

    /** @return array{currentCategory: array{id: string, name: string}|null, proposedCategory: array{id: string, name: string}|null, currentProfessionals: list<array{id: string, name: string|null}>, proposedProfessionals: list<array{id: string, name: string|null}>, assignmentsValid: bool} */
    public function serviceRelationsForReview(ProposedOperation $proposal, TenantContext $context): array
    {
        $service = Service::query()
            ->whereKey($proposal->input['service_id'] ?? '')
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->with(['category:id,name', 'professionals' => fn ($query) => $query->where('professionals.tenant_id', $context->tenant->getKey())->where('professionals.unit_id', $context->unit?->getKey())->orderBy('professionals.name')])
            ->first(['id', 'category_id']);

        if ($proposal->operation_key !== 'service.update' || ! $service instanceof Service) {
            return ['currentCategory' => null, 'proposedCategory' => null, 'currentProfessionals' => [], 'proposedProfessionals' => [], 'assignmentsValid' => false];
        }

        $currentProfessionals = array_values($service->professionals->map(fn (Professional $professional): array => [
            'id' => (string) $professional->getKey(),
            'name' => $professional->name,
        ])->all());
        $currentCategory = $service->category instanceof Category
            ? ['id' => (string) $service->category->getKey(), 'name' => $service->category->name]
            : null;
        $proposedCategoryId = array_key_exists('category_id', $proposal->input) ? $proposal->input['category_id'] : $service->category_id;
        $proposedCategory = $proposedCategoryId === null
            ? null
            : Category::query()
                ->whereKey($proposedCategoryId)
                ->where('tenant_id', $context->tenant->getKey())
                ->where('unit_id', $context->unit?->getKey())
                ->where('type', 'service')
                ->first(['id', 'name']);
        $proposedProfessionalIds = array_key_exists('professional_ids', $proposal->input)
            ? $proposal->input['professional_ids']
            : array_map(fn (array $professional): string => $professional['id'], $currentProfessionals);
        $proposedProfessionals = array_values(Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereIn('id', $proposedProfessionalIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Professional $professional): array => ['id' => (string) $professional->getKey(), 'name' => $professional->name])
            ->all());

        return [
            'currentCategory' => $currentCategory,
            'proposedCategory' => $proposedCategory instanceof Category ? ['id' => (string) $proposedCategory->getKey(), 'name' => $proposedCategory->name] : null,
            'currentProfessionals' => $currentProfessionals,
            'proposedProfessionals' => $proposedProfessionals,
            'assignmentsValid' => ($proposedCategoryId === null || $proposedCategory instanceof Category)
                && count($proposedProfessionals) === count($proposedProfessionalIds),
        ];
    }

    /** @return array{professionals: list<array{id: string, name: string|null}>, professionalAssignmentsValid: bool, serviceAssignmentsValid: bool, currentServices: list<array{id: string, name: string|null}>, proposedServices: list<array{id: string, name: string|null}>} */
    public function reviewDetails(ProposedOperation $proposal, TenantContext $context): array
    {
        $professionalIds = $proposal->input['professional_ids'] ?? [];
        $professionals = Professional::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereIn('id', $professionalIds)
            ->get(['id', 'name'])
            ->keyBy(fn (Professional $professional): string => (string) $professional->getKey());

        $serviceIds = $proposal->input['service_ids'] ?? [];
        $serviceRecords = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereIn('id', $serviceIds)
            ->get(['id', 'name'])
            ->keyBy(fn (Service $service): string => (string) $service->getKey());
        $currentServiceIds = $proposal->operation_key === 'professional.update'
            ? ($proposal->input['_snapshot']['service_ids'] ?? [])
            : [];
        $currentServiceRecords = Service::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('unit_id', $context->unit?->getKey())
            ->whereIn('id', $currentServiceIds)
            ->get(['id', 'name'])
            ->keyBy(fn (Service $service): string => (string) $service->getKey());

        return [
            'professionals' => array_values(array_map(
                fn (string $id): array => [
                    'id' => $id,
                    'name' => $professionals->get($id)?->name,
                ],
                $professionalIds,
            )),
            'professionalAssignmentsValid' => $professionals->count() === count($professionalIds),
            'serviceAssignmentsValid' => $serviceRecords->count() === count($serviceIds),
            'currentServices' => array_values(array_map(
                fn (string $id): array => ['id' => $id, 'name' => $currentServiceRecords->get($id)?->name],
                $currentServiceIds,
            )),
            'proposedServices' => array_values(array_map(
                fn (string $id): array => ['id' => $id, 'name' => $serviceRecords->get($id)?->name],
                $serviceIds,
            )),
        ];
    }

    private function revalidateContext(TenantContext $context): TenantContext
    {
        $fresh = TenantContext::forUser($context->user, (string) $context->tenant->getKey(), $context->unit?->getKey());

        if (! $this->integrationAdmin->allows($fresh->user, $fresh)) {
            throw new AuthorizationException('Integration management is no longer allowed in this context.');
        }

        return $fresh;
    }

    /** @throws AuthorizationException|ConflictHttpException|ModelNotFoundException */
    private function updateCategory(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateCategory $updateCategory): Category
    {
        $category = Category::query()
            ->whereKey($proposal->input['category_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $category instanceof Category) {
            throw new ConflictHttpException('The category is no longer available in this unit. Create a new proposal.');
        }

        try {
            return $updateCategory->handle($actor, $context, $category, [
                'name' => $proposal->input['name'] ?? $category->name,
                'type' => $proposal->input['type'] ?? $category->type,
                'description' => array_key_exists('description', $proposal->input) ? $proposal->input['description'] : $category->description,
                'is_active' => $proposal->input['is_active'] ?? $category->is_active,
            ], (int) $proposal->input['expected_version']);
        } catch (ModelNotFoundException $exception) {
            throw new ConflictHttpException('The category is no longer available in this unit. Create a new proposal.', $exception);
        }
    }

    /** @throws AuthorizationException|ConflictHttpException|ModelNotFoundException */
    private function updateService(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateService $updateService): Service
    {
        $service = Service::query()
            ->whereKey($proposal->input['service_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $service instanceof Service) {
            throw new ConflictHttpException('The service is no longer available in this unit. Create a new proposal.');
        }

        try {
            return $updateService->handle($actor, $context, $service, [
                'name' => $proposal->input['name'] ?? $service->name,
                'description' => array_key_exists('description', $proposal->input) ? $proposal->input['description'] : $service->description,
                'duration_minutes' => $proposal->input['duration_minutes'] ?? $service->duration_minutes,
                'price_cents' => $proposal->input['price_cents'] ?? $service->price_cents,
                'status' => $proposal->input['status'] ?? $service->status,
                ...(array_key_exists('category_id', $proposal->input) ? ['category_id' => $proposal->input['category_id']] : []),
                ...(array_key_exists('professional_ids', $proposal->input) ? ['professional_ids' => $proposal->input['professional_ids']] : []),
            ], (int) $proposal->input['expected_version']);
        } catch (ModelNotFoundException $exception) {
            throw new ConflictHttpException('The service is no longer available in this unit. Create a new proposal.', $exception);
        }
    }

    /** @throws AuthorizationException|ConflictHttpException|ModelNotFoundException */
    private function updateUnitSettings(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateUnitSettings $updateUnitSettings): Unit
    {
        $unit = Unit::query()
            ->whereKey($proposal->unit_id)
            ->where('tenant_id', $proposal->tenant_id)
            ->first();

        if (! $unit instanceof Unit) {
            throw new ConflictHttpException('The unit is no longer available in this workspace. Create a new proposal.');
        }

        return $updateUnitSettings->handle(
            $actor,
            $context,
            [
                'name' => $proposal->input['name'],
                'timezone' => $proposal->input['timezone'],
                'address' => $proposal->input['address'] ?? $unit->address,
                'online_booking_enabled' => $proposal->input['online_booking_enabled'],
                'appointment_sales_automation_enabled' => $proposal->input['appointment_sales_automation_enabled'],
            ],
            (int) $proposal->input['expected_version'],
        );
    }

    private function updateAvailabilityRule(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateAvailabilityRule $updateAvailabilityRule): AvailabilityRule
    {
        $rule = AvailabilityRule::query()
            ->whereKey($proposal->input['availability_rule_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $rule instanceof AvailabilityRule) {
            throw new ConflictHttpException('The availability rule is no longer available in this unit. Create a new proposal.');
        }

        return $updateAvailabilityRule->handle($actor, $context, $rule, [
            'professional_id' => $proposal->input['professional_id'],
            'weekday' => $proposal->input['weekday'],
            'starts_at' => $proposal->input['starts_at'],
            'ends_at' => $proposal->input['ends_at'],
            'timezone' => $proposal->input['timezone'],
            'status' => $proposal->input['status'] ?? $rule->status,
            'lock_version' => (int) $proposal->input['expected_version'],
        ]);
    }

    private function deleteAvailabilityRule(ProposedOperation $proposal, User $actor, TenantContext $context, DeleteAvailabilityRule $deleteAvailabilityRule): AvailabilityRule
    {
        $rule = AvailabilityRule::query()
            ->whereKey($proposal->input['availability_rule_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $rule instanceof AvailabilityRule) {
            throw new ConflictHttpException('The availability rule is no longer available in this unit. Create a new proposal.');
        }

        return $deleteAvailabilityRule->handle($actor, $context, $rule, (int) $proposal->input['expected_version']);
    }

    private function updateScheduleBlock(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateScheduleBlock $updateScheduleBlock): ScheduleBlock
    {
        $block = ScheduleBlock::query()
            ->whereKey($proposal->input['schedule_block_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $block instanceof ScheduleBlock) {
            throw new ConflictHttpException('The schedule block is no longer available in this unit. Create a new proposal.');
        }

        return $updateScheduleBlock->handle($actor, $context, $block, [
            'professional_id' => $proposal->input['professional_id'] ?? null,
            'starts_at' => $proposal->input['starts_at'],
            'ends_at' => $proposal->input['ends_at'],
            'timezone' => $proposal->input['timezone'],
            'reason' => $proposal->input['reason'] ?? null,
            'status' => $proposal->input['status'] ?? $block->status,
            'lock_version' => (int) $proposal->input['expected_version'],
        ]);
    }

    private function deleteScheduleBlock(ProposedOperation $proposal, User $actor, TenantContext $context, DeleteScheduleBlock $deleteScheduleBlock): ScheduleBlock
    {
        $block = ScheduleBlock::query()
            ->whereKey($proposal->input['schedule_block_id'])
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $block instanceof ScheduleBlock) {
            throw new ConflictHttpException('The schedule block is no longer available in this unit. Create a new proposal.');
        }

        return $deleteScheduleBlock->handle($actor, $context, $block, (int) $proposal->input['expected_version']);
    }

    private function updateBookingSettings(ProposedOperation $proposal, User $actor, TenantContext $context, UpdateOnlineBookingSettings $updateOnlineBookingSettings): Unit
    {
        $unit = Unit::query()
            ->whereKey($proposal->unit_id)
            ->where('tenant_id', $proposal->tenant_id)
            ->first();

        if (! $unit instanceof Unit) {
            throw new ConflictHttpException('The booking unit is no longer available. Create a new proposal.');
        }

        $setting = OnlineBookingSetting::query()
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();
        $site = OnlineBookingSite::query()
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();
        $input = $proposal->input;
        $serviceIds = array_key_exists('service_ids', $input)
            ? $input['service_ids']
            : Service::query()
                ->where('tenant_id', $proposal->tenant_id)
                ->where('unit_id', $proposal->unit_id)
                ->where('status', 'active')
                ->where('online_booking_enabled', true)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
        $professionalIds = array_key_exists('professional_ids', $input)
            ? $input['professional_ids']
            : Professional::query()
                ->where('tenant_id', $proposal->tenant_id)
                ->where('unit_id', $proposal->unit_id)
                ->where('status', 'active')
                ->where('online_booking_enabled', true)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();

        return $updateOnlineBookingSettings->handle($actor, $context, [
            'lock_version' => (int) $input['expected_version'],
            'online_booking_enabled' => $input['online_booking_enabled'] ?? $unit->online_booking_enabled,
            'service_ids' => $serviceIds,
            'professional_ids' => $professionalIds,
            'public_slug' => $input['public_slug'] ?? ($setting->public_slug ?? $unit->slug),
            'public_domain_id' => array_key_exists('public_domain_id', $input) ? $input['public_domain_id'] : $setting?->public_domain_id,
            'description' => array_key_exists('description', $input) ? $input['description'] : $setting?->description,
            'whatsapp_phone' => array_key_exists('whatsapp_phone', $input) ? $input['whatsapp_phone'] : $setting?->whatsapp_phone,
            'phone' => array_key_exists('phone', $input) ? $input['phone'] : $setting?->phone,
            'instagram_url' => array_key_exists('instagram_url', $input) ? $input['instagram_url'] : $setting?->instagram_url,
            'facebook_url' => array_key_exists('facebook_url', $input) ? $input['facebook_url'] : $setting?->facebook_url,
            'website_url' => array_key_exists('website_url', $input) ? $input['website_url'] : $setting?->website_url,
            'brand_color' => array_key_exists('brand_color', $input) ? $input['brand_color'] : ($setting->brand_color ?? '#2563eb'),
            'booking_flow' => $input['booking_flow'] ?? ($setting->booking_flow ?? 'service_first'),
            'minimum_notice_minutes' => $input['minimum_notice_minutes'] ?? ($setting->minimum_notice_minutes ?? 0),
            'public_hours' => array_key_exists('public_hours', $input) ? $input['public_hours'] : $setting?->public_hours,
            'template_key' => $input['template_key'] ?? ($site->template_key ?? 'essential'),
        ]);
    }

    private function unpublishBooking(ProposedOperation $proposal, User $actor, TenantContext $context, UnpublishOnlineBookingSite $unpublishOnlineBookingSite): OnlineBookingSite
    {
        $site = OnlineBookingSite::query()
            ->where('tenant_id', $proposal->tenant_id)
            ->where('unit_id', $proposal->unit_id)
            ->first();

        if (! $site instanceof OnlineBookingSite) {
            throw new ConflictHttpException('The booking site is no longer available. Create a new proposal.');
        }

        return $unpublishOnlineBookingSite->handle($actor, $context, (int) $proposal->input['expected_version']);
    }

    private function requiresRefreshOnConflict(string $operation): bool
    {
        return in_array($operation, [
            'category.update',
            'professional.update',
            'service.update',
            'unit.update',
            'availability_rule.update',
            'availability_rule.delete',
            'schedule_block.update',
            'schedule_block.delete',
            'booking.settings.update',
            'booking.draft.update',
            'booking.publish',
            'booking.unpublish',
        ], true);
    }

    private function refreshFailureMessage(string $operation): string
    {
        return match (true) {
            $operation === 'category.update' => 'The category is no longer available in this unit. Review the current category and create a new proposal.',
            $operation === 'professional.update' => 'The professional is no longer available in this unit. Review the current professional and create a new proposal.',
            $operation === 'service.update' => 'The service or its assignments are no longer available in this unit. Review the current service and create a new proposal.',
            str_starts_with($operation, 'availability_rule.') => 'The availability rule is no longer available in this unit. Review the current rule and create a new proposal.',
            str_starts_with($operation, 'schedule_block.') => 'The schedule block is no longer available in this unit. Review the current block and create a new proposal.',
            str_starts_with($operation, 'booking.') => 'The booking configuration changed or is no longer available. Review the current configuration and create a new proposal.',
            default => 'The unit settings changed after this proposal was created. Review the current settings and create a new proposal.',
        };
    }

    private function assertSourceContext(IntegrationCredential|OAuthGrant|User $source, TenantContext $context): void
    {
        if ($source instanceof User) {
            if (! $context->user->is($source) || ! $this->integrationAdmin->allows($source, $context)) {
                throw new AuthorizationException('Internal assistant proposals require an active administrator context.');
            }

            return;
        }

        if ($source instanceof OAuthGrant) {
            $this->oauthGrantPolicy->assertActiveForContext($source, $context->user, $context);

            return;
        }

        $this->assertCredentialContext($source, $context);
    }

    private function assertCredentialContext(IntegrationCredential $credential, TenantContext $context): void
    {
        $credential->refresh();
        $token = $credential->passportToken()->first();
        $capabilities = $credential->capabilities;
        sort($capabilities);
        $tokenScopes = $token instanceof Token ? $token->scopes : [];
        sort($tokenScopes);
        abort_unless(
            $credential->user_id === (string) $context->user->getKey()
                && $credential->tenant_id === (string) $context->tenant->getKey()
                && $credential->unit_id === (string) $context->unit?->getKey()
                && $credential->revoked_at === null
                && $credential->expires_at->isFuture(),
            403,
        );
        abort_unless(
            $token instanceof Token
                && ! $token->revoked
                && $token->expires_at?->isFuture()
                && $token->user_id === (string) $context->user->getKey()
                && $tokenScopes === $capabilities
                && $capabilities === ['operations:propose'],
            403,
        );
    }

    private function assertProposalContext(ProposedOperation $proposal, User $actor, TenantContext $context): void
    {
        if (! $context->user->is($actor)
            || $proposal->actor_id !== (string) $actor->getKey()
            || $proposal->tenant_id !== (string) $context->tenant->getKey()
            || $proposal->unit_id !== (string) $context->unit?->getKey()) {
            throw (new ModelNotFoundException)->setModel(ProposedOperation::class);
        }

        if ($proposal->oauth_grant_id !== null) {
            if ($proposal->credential_id !== null || ! $proposal->oauthGrant instanceof OAuthGrant) {
                throw (new ModelNotFoundException)->setModel(OAuthGrant::class);
            }

            $this->oauthGrantPolicy->assertActiveForContext($proposal->oauthGrant, $actor, $context);

            return;
        }

        if ($proposal->source === ProposedOperation::SOURCE_INTERNAL_ASSISTANT) {
            if ($proposal->getAttribute('credential_id') !== null || $proposal->getAttribute('oauth_grant_id') !== null
                || ! $this->integrationAdmin->allows($actor, $context)) {
                throw (new ModelNotFoundException)->setModel(ProposedOperation::class);
            }

            return;
        }

        $credential = $proposal->credential;
        if ($proposal->credential_id === null || ! $credential instanceof IntegrationCredential) {
            throw (new ModelNotFoundException)->setModel(IntegrationCredential::class);
        }

        $this->assertCredentialContext($credential, $context);
    }

    private function sourceColumn(IntegrationCredential|OAuthGrant|User $source): string
    {
        return match (true) {
            $source instanceof OAuthGrant => 'oauth_grant_id',
            $source instanceof IntegrationCredential => 'credential_id',
            default => 'actor_id',
        };
    }

    private function sourceType(IntegrationCredential|OAuthGrant|User $source): string
    {
        return match (true) {
            $source instanceof OAuthGrant => ProposedOperation::SOURCE_OAUTH,
            $source instanceof IntegrationCredential => ProposedOperation::SOURCE_CREDENTIAL,
            default => ProposedOperation::SOURCE_INTERNAL_ASSISTANT,
        };
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function canonicalize(array $input): array
    {
        ksort($input);
        foreach ($input as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $input[$key] = $this->canonicalize($value);
            }
        }

        return $input;
    }

    /** @param array<string, mixed> $input */
    private function inputHash(array $input): string
    {
        return hash('sha256', json_encode($this->canonicalize($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
