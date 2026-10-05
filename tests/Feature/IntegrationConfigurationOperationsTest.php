<?php

use App\Actions\Calendar\CreateAvailabilityRule;
use App\Actions\Calendar\CreateScheduleBlock;
use App\Actions\Calendar\DeleteAvailabilityRule;
use App\Actions\Calendar\DeleteScheduleBlock;
use App\Actions\Calendar\UpdateAvailabilityRule;
use App\Actions\Calendar\UpdateScheduleBlock;
use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\UpdateCategory;
use App\Actions\Identity\OnboardTenant;
use App\Actions\Identity\UpdateUnitSettings;
use App\Actions\OnlineBooking\PublishOnlineBookingSite;
use App\Actions\OnlineBooking\SaveOnlineBookingDraft;
use App\Actions\OnlineBooking\UnpublishOnlineBookingSite;
use App\Actions\OnlineBooking\UpdateOnlineBookingSettings;
use App\Actions\Services\CreateService;
use App\Actions\Services\UpdateService;
use App\Http\Requests\Integrations\ProposeServiceOperationRequest;
use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\Integrations\ProposedOperation;
use App\Models\OnlineBookingSite;
use App\Models\Professional;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\IntegrationCapabilityCatalog;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** @return array{0: User, 1: Tenant, 2: Unit, 3: Service, 4: Professional} */
function configurationOperationsWorkspace(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Configuration operations '.Str::random(8),
        'slug' => 'configuration-operations-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => false,
    ]);
    $professional = Professional::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'online_booking_enabled' => false,
    ]);
    $professional->services()->attach($service->getKey(), [
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
    ]);

    return [$owner, $tenant, $unit, $service, $professional];
}

/** @param array<string, mixed> $input */
function confirmConfigurationProposal(User $owner, TenantContext $context, string $operation, array $input, string $key): ProposedOperation
{
    $service = app(ProposedOperationService::class);
    $proposal = $service->propose($owner, $context, $operation, $input, $key);

    return $service->confirm(
        $proposal,
        $owner,
        $context,
        app(CreateService::class),
        app(CreateCategory::class),
        app(UpdateCategory::class),
        app(UpdateService::class),
        app(UpdateUnitSettings::class),
        app(CreateAvailabilityRule::class),
        app(UpdateAvailabilityRule::class),
        app(DeleteAvailabilityRule::class),
        app(CreateScheduleBlock::class),
        app(UpdateScheduleBlock::class),
        app(DeleteScheduleBlock::class),
        app(UpdateOnlineBookingSettings::class),
        app(SaveOnlineBookingDraft::class),
        app(PublishOnlineBookingSite::class),
        app(UnpublishOnlineBookingSite::class),
    );
}

it('confirms availability rule and schedule block proposals with scoped optimistic locking', function (): void {
    [$owner, $tenant, $unit, , $professional] = configurationOperationsWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    $rule = confirmConfigurationProposal($owner, $context, 'availability_rule.create', [
        'professional_id' => $professional->getKey(),
        'weekday' => 1,
        'starts_at' => '09:00',
        'ends_at' => '18:00',
        'timezone' => 'America/Sao_Paulo',
    ], 'availability-create');
    $availabilityRule = AvailabilityRule::query()->findOrFail($rule->result_reference['resource_id']);
    expect($rule->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($availabilityRule->lock_version)->toBe(0);

    $rule = confirmConfigurationProposal($owner, $context, 'availability_rule.update', [
        'availability_rule_id' => $availabilityRule->getKey(),
        'expected_version' => 0,
        'professional_id' => $professional->getKey(),
        'weekday' => 2,
        'starts_at' => '10:00',
        'ends_at' => '19:00',
        'timezone' => 'America/Sao_Paulo',
    ], 'availability-update');
    expect($rule->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($availabilityRule->fresh()->weekday)->toBe(2)
        ->and($availabilityRule->fresh()->lock_version)->toBe(1);

    $rule = confirmConfigurationProposal($owner, $context, 'availability_rule.delete', [
        'availability_rule_id' => $availabilityRule->getKey(),
        'expected_version' => 1,
    ], 'availability-delete');
    expect($rule->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($availabilityRule->fresh()->status)->toBe('inactive')
        ->and($availabilityRule->fresh()->lock_version)->toBe(2);

    $block = confirmConfigurationProposal($owner, $context, 'schedule_block.create', [
        'professional_id' => $professional->getKey(),
        'starts_at' => '2026-10-01 09:00:00',
        'ends_at' => '2026-10-01 10:00:00',
        'timezone' => 'America/Sao_Paulo',
        'reason' => 'Reunião',
    ], 'block-create');
    $scheduleBlock = ScheduleBlock::query()->findOrFail($block->result_reference['resource_id']);
    expect($block->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($scheduleBlock->lock_version)->toBe(0);

    $block = confirmConfigurationProposal($owner, $context, 'schedule_block.update', [
        'schedule_block_id' => $scheduleBlock->getKey(),
        'expected_version' => 0,
        'professional_id' => $professional->getKey(),
        'starts_at' => '2026-10-01 11:00:00',
        'ends_at' => '2026-10-01 12:00:00',
        'timezone' => 'America/Sao_Paulo',
        'reason' => 'Reunião reagendada',
    ], 'block-update');
    expect($block->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($scheduleBlock->fresh()->reason)->toBe('Reunião reagendada')
        ->and($scheduleBlock->fresh()->lock_version)->toBe(1);

    $block = confirmConfigurationProposal($owner, $context, 'schedule_block.delete', [
        'schedule_block_id' => $scheduleBlock->getKey(),
        'expected_version' => 1,
    ], 'block-delete');
    expect($block->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($scheduleBlock->fresh()->status)->toBe('cancelled')
        ->and($scheduleBlock->fresh()->lock_version)->toBe(2);
});

it('confirms booking settings, draft, publication, and unpublication proposals', function (): void {
    [$owner, $tenant, $unit, $service, $professional] = configurationOperationsWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());

    $settings = confirmConfigurationProposal($owner, $context, 'booking.settings.update', [
        'expected_version' => 0,
        'online_booking_enabled' => true,
        'service_ids' => [$service->getKey()],
        'professional_ids' => [$professional->getKey()],
        'public_slug' => 'configuracao-online',
    ], 'booking-settings');
    expect($settings->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($unit->fresh()->online_booking_enabled)->toBeTrue()
        ->and($service->fresh()->online_booking_enabled)->toBeTrue()
        ->and($professional->fresh()->online_booking_enabled)->toBeTrue();

    $draft = confirmConfigurationProposal($owner, $context, 'booking.draft.update', [
        'expected_revision' => 0,
        'content' => [
            'schema_version' => 1,
            'service_ids' => [$service->getKey()],
            'professional_ids' => [$professional->getKey()],
            'sections' => [['key' => 'services', 'enabled' => true]],
        ],
    ], 'booking-draft');
    expect($draft->status)->toBe(ProposedOperation::STATUS_SUCCEEDED);

    $site = OnlineBookingSite::query()->where('tenant_id', $tenant->getKey())->where('unit_id', $unit->getKey())->firstOrFail();
    $publish = confirmConfigurationProposal($owner, $context, 'booking.publish', [
        'expected_revision' => 1,
    ], 'booking-publish');
    expect($publish->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($site->fresh()->status->value)->toBe('published');

    $site->refresh();
    $unpublish = confirmConfigurationProposal($owner, $context, 'booking.unpublish', [
        'expected_version' => $site->lock_version,
    ], 'booking-unpublish');
    expect($unpublish->status)->toBe(ProposedOperation::STATUS_SUCCEEDED)
        ->and($site->fresh()->status->value)->toBe('unpublished');
});

it('marks an existing configuration proposal for refresh when its target version is stale', function (): void {
    [$owner, $tenant, $unit, , $professional] = configurationOperationsWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $created = app(ProposedOperationService::class)->propose($owner, $context, 'availability_rule.create', [
        'professional_id' => $professional->getKey(),
        'weekday' => 1,
        'starts_at' => '09:00',
        'ends_at' => '18:00',
        'timezone' => 'America/Sao_Paulo',
    ], 'stale-create');
    $rule = AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'lock_version' => 2,
    ]);
    $proposal = app(ProposedOperationService::class)->propose($owner, $context, 'availability_rule.delete', [
        'availability_rule_id' => $rule->getKey(),
        'expected_version' => 1,
    ], 'stale-delete');
    $review = app(ProposedOperationService::class)->operationReview($proposal, $context);

    expect($created->status)->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION)
        ->and($review['expectedVersionMatches'])->toBeFalse();
});

it('does not apply a stale configuration proposal and marks it for refresh', function (): void {
    [$owner, $tenant, $unit, , $professional] = configurationOperationsWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $rule = AvailabilityRule::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'professional_id' => $professional->getKey(),
        'lock_version' => 1,
    ]);
    $proposal = app(ProposedOperationService::class)->propose($owner, $context, 'availability_rule.update', [
        'availability_rule_id' => $rule->getKey(),
        'expected_version' => 0,
        'professional_id' => $professional->getKey(),
        'weekday' => 4,
        'starts_at' => '09:00',
        'ends_at' => '18:00',
        'timezone' => 'America/Sao_Paulo',
    ], 'stale-update');

    expect(fn (): ProposedOperation => confirmConfigurationProposal($owner, $context, 'availability_rule.update', [
        'availability_rule_id' => $rule->getKey(),
        'expected_version' => 0,
        'professional_id' => $professional->getKey(),
        'weekday' => 4,
        'starts_at' => '09:00',
        'ends_at' => '18:00',
        'timezone' => 'America/Sao_Paulo',
    ], 'stale-update'))->toThrow(ConflictHttpException::class);

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH)
        ->and($rule->fresh()->weekday)->not->toBe(4)
        ->and($rule->fresh()->lock_version)->toBe(1);
});

it('validates only advertised configuration operations against the tenant and unit context', function (): void {
    [$owner, $tenant, $unit, $service, $professional] = configurationOperationsWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $category = Category::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'type' => 'service',
    ]);

    $payloads = [
        'category.create' => [
            'name' => 'Serviços', 'type' => 'service', 'is_active' => true,
        ],
        'category.update' => [
            'category_name' => $category->name,
            'name' => 'Serviços atualizados',
        ],
        'professional.create' => [
            'name' => 'Novo profissional', 'status' => 'active', 'service_names' => [$service->name],
        ],
        'professional.update' => [
            'professional_name' => $professional->name,
            'status' => 'inactive',
        ],
        'service.create' => [
            'name' => 'Novo serviço', 'duration_minutes' => 45, 'price_cents' => 5000,
            'status' => 'active', 'professional_names' => [$professional->name], 'category_name' => $category->name,
        ],
        'service.update' => [
            'service_name' => $service->name,
            'name' => 'Serviço atualizado', 'duration_minutes' => 60, 'price_cents' => 6500,
            'status' => 'active', 'category_name' => $category->name, 'professional_names' => [$professional->name],
        ],
        'unit.update' => [
            'name' => 'Unidade atualizada',
            'timezone' => 'America/Sao_Paulo', 'online_booking_enabled' => false,
            'appointment_sales_automation_enabled' => false,
        ],
    ];

    $advertisedOperations = array_map(
        static fn (array $operation): string => $operation['operation'],
        app(IntegrationCapabilityCatalog::class)->operations(),
    );

    expect($advertisedOperations)->toBe(array_keys($payloads));

    foreach ($payloads as $operation => $input) {
        $request = ProposeServiceOperationRequest::create('/api/v1/operations', 'POST', [
            'operation' => $operation,
            'input' => $input,
        ]);
        $request->setUserResolver(fn (): User => $owner);
        $request->attributes->set(TenantContext::class, $context);

        expect(Validator::make($request->all(), $request->rules())->passes())->toBeTrue($operation);
    }

    foreach ([
        'availability_rule.create', 'availability_rule.update', 'availability_rule.delete',
        'schedule_block.create', 'schedule_block.update', 'schedule_block.delete',
        'booking.settings.update', 'booking.draft.update', 'booking.publish', 'booking.unpublish',
    ] as $operation) {
        $request = ProposeServiceOperationRequest::create('/api/v1/operations', 'POST', [
            'operation' => $operation,
        ]);
        $request->setUserResolver(fn (): User => $owner);
        $request->attributes->set(TenantContext::class, $context);
        $validator = Validator::make($request->all(), $request->rules());

        expect($validator->fails())->toBeTrue($operation)
            ->and($validator->errors()->has('operation'))->toBeTrue($operation);
    }
});
