<?php

use App\Actions\Identity\OnboardTenant;
use App\Actions\Marketing\Retention\BuildRetentionCampaignAudience;
use App\Actions\Marketing\Retention\DispatchRetentionCampaign;
use App\Actions\Marketing\Retention\ProcessRetentionCampaignDeliveries;
use App\Models\AuditEvent;
use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use App\Models\IdempotencyKey;
use App\Models\LegalHold;
use App\Models\RetentionCampaign;
use App\Models\RetentionCampaignRecipient;
use App\Models\RetentionDelivery;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/** @return array{0: User, 1: Tenant, 2: Unit} */
function campaignWorkspace(): array
{
    $owner = User::factory()->create();
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Campaign '.Str::random(8),
        'slug' => 'campaign-'.Str::lower(Str::random(8)),
    ]);

    return [$owner, $tenant, $tenant->units()->firstOrFail()];
}

/** @return array<string, mixed> */
function campaignPayload(): array
{
    return [
        'name' => 'Clientes inativos',
        'channel' => 'whatsapp',
        'purpose' => 'retention',
        'segment_definition' => ['inactive_days' => 90, 'retention_status' => 'at_risk'],
        'message' => 'Sentimos sua falta. Vamos agendar seu retorno?',
    ];
}

function campaignFor(User $owner, Tenant $tenant, Unit $unit, array $attributes = []): RetentionCampaign
{
    return RetentionCampaign::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'created_by_user_id' => $owner->getKey(),
        'channel' => 'whatsapp',
        ...$attributes,
    ]);
}

function retainedCustomer(Tenant $tenant, Unit $unit, array $attributes = []): Customer
{
    return Customer::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'last_activity_at' => now()->subDays(120),
        'retention_status' => 'at_risk',
        ...$attributes,
    ]);
}

function grantConsent(Customer $customer, string $channel = 'whatsapp'): CustomerCommunicationPreference
{
    return CustomerCommunicationPreference::factory()->create([
        'tenant_id' => $customer->tenant_id,
        'unit_id' => $customer->unit_id,
        'customer_id' => $customer->getKey(),
        'channel' => $channel,
        'opted_in' => true,
        'consented_at' => now()->subDay(),
        'revoked_at' => null,
    ]);
}

it('creates a campaign idempotently and accepts the defined lifecycle', function () {
    [$owner, $tenant] = campaignWorkspace();
    $headers = ['X-Idempotency-Key' => 'retention-campaign-create-'.Str::uuid7()];

    $first = $this->actingAs($owner)
        ->withHeaders($headers)
        ->postJson(route('retention.campaigns.store'), campaignPayload())
        ->assertCreated();
    $campaignId = $first->json('campaign.id');

    $this->actingAs($owner)
        ->withHeaders($headers)
        ->postJson(route('retention.campaigns.store'), campaignPayload())
        ->assertCreated()
        ->assertJsonPath('campaign.id', $campaignId);

    $campaign = RetentionCampaign::query()->findOrFail($campaignId);
    expect(RetentionCampaign::query()->where('tenant_id', $tenant->getKey())->count())->toBe(1)
        ->and(IdempotencyKey::query()->where('tenant_id', $tenant->getKey())->where('key', $headers['X-Idempotency-Key'])->exists())->toBeTrue();

    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'retention-campaign-status-'.Str::uuid7())
        ->patchJson(route('retention.campaigns.status', $campaign), ['status' => 'active'])
        ->assertSuccessful()
        ->assertJsonPath('campaign.status', 'active');
    $campaign->refresh();
    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'retention-campaign-status-'.Str::uuid7())
        ->patchJson(route('retention.campaigns.status', $campaign), ['status' => 'paused'])
        ->assertSuccessful()
        ->assertJsonPath('campaign.status', 'paused');
    $campaign->refresh();
    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'retention-campaign-status-'.Str::uuid7())
        ->patchJson(route('retention.campaigns.status', $campaign), ['status' => 'completed'])
        ->assertSuccessful()
        ->assertJsonPath('campaign.status', 'completed');
    $campaign->refresh();
    $this->actingAs($owner)
        ->withHeader('X-Idempotency-Key', 'retention-campaign-status-'.Str::uuid7())
        ->patchJson(route('retention.campaigns.status', $campaign), ['status' => 'active'])
        ->assertForbidden();
});

it('snapshots an inactive consented audience once and records exclusions for consent and legal holds', function () {
    [$owner, $tenant, $unit] = campaignWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $campaign = campaignFor($owner, $tenant, $unit, ['segment_definition' => ['inactive_days' => 90, 'retention_status' => 'at_risk']]);
    $eligible = retainedCustomer($tenant, $unit);
    $optedOut = retainedCustomer($tenant, $unit);
    $held = retainedCustomer($tenant, $unit);
    $recent = retainedCustomer($tenant, $unit, ['last_activity_at' => now()->subDays(10)]);
    grantConsent($eligible);
    CustomerCommunicationPreference::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $optedOut->getKey(),
        'channel' => 'whatsapp',
        'opted_in' => false,
        'consented_at' => null,
        'revoked_at' => now(),
    ]);
    grantConsent($held);
    LegalHold::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $held->getKey(),
        'placed_by_user_id' => $owner->getKey(),
    ]);
    grantConsent($recent);

    $dryRun = app(BuildRetentionCampaignAudience::class)->handle($owner, $context, $campaign, dryRun: true);
    expect($dryRun)->toMatchArray(['selected' => 1, 'existing' => false])
        ->and($campaign->fresh()->audience_snapshot_at)->toBeNull();

    $first = app(BuildRetentionCampaignAudience::class)->handle($owner, $context, $campaign);
    $second = app(BuildRetentionCampaignAudience::class)->handle($owner, $context, $campaign);

    expect($first)->toMatchArray(['selected' => 1, 'existing' => false])
        ->and($second)->toMatchArray(['selected' => 1, 'existing' => true])
        ->and(RetentionCampaignRecipient::query()->where('retention_campaign_id', $campaign->getKey())->count())->toBe(3)
        ->and(RetentionCampaignRecipient::query()->where('retention_campaign_id', $campaign->getKey())->where('customer_id', $eligible->getKey())->value('status'))->toBe('selected')
        ->and(RetentionCampaignRecipient::query()->where('retention_campaign_id', $campaign->getKey())->where('customer_id', $optedOut->getKey())->value('status'))->toBe('excluded')
        ->and(RetentionCampaignRecipient::query()->where('retention_campaign_id', $campaign->getKey())->where('customer_id', $held->getKey())->value('status'))->toBe('excluded')
        ->and(AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('action', 'retention.campaign.audience_snapshotted')->count())->toBe(1);
});

it('keeps dispatch idempotent and blocks a recipient who opts out after the audience snapshot', function () {
    [$owner, $tenant, $unit] = campaignWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $campaign = campaignFor($owner, $tenant, $unit, ['status' => 'active']);
    $allowed = retainedCustomer($tenant, $unit);
    $withdrawn = retainedCustomer($tenant, $unit);
    grantConsent($allowed);
    $preference = grantConsent($withdrawn);
    app(BuildRetentionCampaignAudience::class)->handle($owner, $context, $campaign);
    $preference->forceFill(['opted_in' => false, 'revoked_at' => now()])->save();

    $first = app(DispatchRetentionCampaign::class)->handle($owner, $context, $campaign);
    $second = app(DispatchRetentionCampaign::class)->handle($owner, $context, $campaign);

    expect($first)->toMatchArray(['queued' => 1, 'blocked' => 1, 'existing' => 0])
        ->and($second['existing'])->toBe(2)
        ->and(RetentionDelivery::query()->where('retention_campaign_id', $campaign->getKey())->count())->toBe(2)
        ->and(RetentionDelivery::query()->where('retention_campaign_id', $campaign->getKey())->where('customer_id', $allowed->getKey())->value('status'))->toBe('pending')
        ->and(RetentionDelivery::query()->where('retention_campaign_id', $campaign->getKey())->where('customer_id', $withdrawn->getKey())->value('status'))->toBe('blocked');
});

it('processes internal deliveries safely, rechecks legal restrictions, and retries failed rows without a provider', function () {
    [$owner, $tenant, $unit] = campaignWorkspace();
    $context = TenantContext::forUser($owner, $tenant->getKey(), $unit->getKey());
    $campaign = campaignFor($owner, $tenant, $unit, ['status' => 'active']);
    $allowed = retainedCustomer($tenant, $unit);
    $heldAfterSnapshot = retainedCustomer($tenant, $unit);
    grantConsent($allowed);
    grantConsent($heldAfterSnapshot);
    app(BuildRetentionCampaignAudience::class)->handle($owner, $context, $campaign);
    app(DispatchRetentionCampaign::class)->handle($owner, $context, $campaign);
    LegalHold::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'customer_id' => $heldAfterSnapshot->getKey(),
        'placed_by_user_id' => $owner->getKey(),
    ]);

    $processed = app(ProcessRetentionCampaignDeliveries::class)->handle($owner, $context, $campaign);
    $allowedDelivery = RetentionDelivery::query()->where('customer_id', $allowed->getKey())->firstOrFail();
    $heldDelivery = RetentionDelivery::query()->where('customer_id', $heldAfterSnapshot->getKey())->firstOrFail();

    expect($processed)->toMatchArray(['sent' => 1, 'blocked' => 1, 'dry_run' => false])
        ->and($allowedDelivery->status)->toBe('sent')
        ->and($allowedDelivery->attempts)->toBe(1)
        ->and($heldDelivery->status)->toBe('blocked');

    $allowedDelivery->forceFill([
        'status' => 'failed',
        'attempts' => 1,
        'last_error' => 'Temporary internal handoff failure',
        'available_at' => now()->subMinute(),
        'sent_at' => null,
    ])->save();

    $retried = app(ProcessRetentionCampaignDeliveries::class)->handle($owner, $context, $campaign);
    $allowedDelivery->refresh();

    expect($retried['sent'])->toBe(1)
        ->and($allowedDelivery->status)->toBe('sent')
        ->and($allowedDelivery->attempts)->toBe(2)
        ->and($allowedDelivery->last_error)->toBeNull();
});

it('does not allow another tenant to inspect or operate a campaign', function () {
    [$owner, $tenant, $unit] = campaignWorkspace();
    [$otherOwner] = campaignWorkspace();
    $campaign = campaignFor($owner, $tenant, $unit);

    $this->actingAs($otherOwner)
        ->postJson(route('retention.campaigns.audience', $campaign), ['dry_run' => true])
        ->assertForbidden();
});
