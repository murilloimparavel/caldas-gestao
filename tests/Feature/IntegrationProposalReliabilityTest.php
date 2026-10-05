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
use App\Models\Integrations\IntegrationCredential;
use App\Models\Integrations\ProposedOperation;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\ProposedOperationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Cache::store('array')->flush();
    Passport::loadKeysFrom(reliabilityPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('Proposal reliability tests', 'users');
});

afterEach(function (): void {
    Cache::store('array')->flush();
});

afterAll(function (): void {
    $directory = reliabilityPassportKeyDirectory();
    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function reliabilityPassportKeyDirectory(): string
{
    static $directory;
    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/proposal-reliability-'.Str::uuid();
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

/** @return array{User, Tenant, Unit, IntegrationCredential, string, Token} */
function reliabilityProposalContext(): array
{
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($owner, [
        'name' => 'Reliability test '.Str::random(8),
        'slug' => 'reliability-test-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    $issued = $owner->createToken('Proposal reliability', ['operations:propose']);
    $token = $issued->getToken();
    $credential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $token->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Proposal reliability',
        'capabilities' => ['operations:propose'],
        'expires_at' => $token->expires_at,
    ]);

    return [$owner, $tenant, $unit, $credential, $issued->accessToken, $token];
}

/** @return array{operation: string, input: array{name: string, duration_minutes: int, price_cents: int}} */
function reliabilityProposalInput(): array
{
    return [
        'operation' => 'service.create',
        'input' => [
            'name' => 'Corte confiável',
            'duration_minutes' => 30,
            'price_cents' => 4500,
        ],
    ];
}

it('shares the proposal rate limit across credentials for one admin and isolates other admins', function (): void {
    [$owner, $tenant, $unit, , $firstToken, $firstPassportToken] = reliabilityProposalContext();
    $secondIssued = $owner->createToken('Proposal reliability second credential', ['operations:propose']);
    $secondCredential = IntegrationCredential::query()->create([
        'id' => (string) Str::uuid(),
        'passport_token_id' => $secondIssued->getToken()->getKey(),
        'user_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'label' => 'Proposal reliability second credential',
        'capabilities' => ['operations:propose'],
        'expires_at' => $secondIssued->getToken()->expires_at,
    ]);

    for ($call = 0; $call < 20; $call++) {
        $token = $call % 2 === 0 ? $firstToken : $secondIssued->accessToken;
        Auth::forgetGuards();
        $this->withToken($token)
            ->withHeader('X-Idempotency-Key', 'rate-limit-admin-'.$call)
            ->postJson('/api/v1/operations', reliabilityProposalInput())
            ->assertStatus(202);
    }

    Auth::forgetGuards();
    $limitedResponse = $this->withToken($secondIssued->accessToken)
        ->withHeader('X-Idempotency-Key', 'rate-limit-admin-21')
        ->postJson('/api/v1/operations', reliabilityProposalInput())
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '20')
        ->assertHeader('X-RateLimit-Remaining', '0');

    expect((int) $limitedResponse->headers->get('Retry-After'))->toBeGreaterThan(0);

    [$otherOwner, , , , $otherToken] = reliabilityProposalContext();
    expect($otherOwner->is($owner))->toBeFalse()
        ->and($firstPassportToken->fresh()->revoked)->toBeFalse();

    Auth::forgetGuards();
    $this->withToken($otherToken)
        ->withHeader('X-Idempotency-Key', 'rate-limit-other-admin-1')
        ->postJson('/api/v1/operations', reliabilityProposalInput())
        ->assertStatus(202)
        ->assertHeader('X-RateLimit-Limit', '20');
});

it('uses the canonical pending confirmation status as the database default', function (): void {
    [$owner, $tenant, $unit, $credential] = reliabilityProposalContext();
    $id = (string) Str::uuid();
    $now = now();

    DB::table('proposed_operations')->insert([
        'id' => $id,
        'credential_id' => $credential->getKey(),
        'actor_id' => $owner->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'operation_key' => 'service.create',
        'input' => json_encode(reliabilityProposalInput()['input'], JSON_THROW_ON_ERROR),
        'input_hash' => hash('sha256', 'canonical input'),
        'idempotency_key_hash' => hash('sha256', $id),
        'expires_at' => $now->copy()->addMinutes(10),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(ProposedOperation::query()->findOrFail($id)->status)
        ->toBe(ProposedOperation::STATUS_PENDING_CONFIRMATION);
});

it('records a stable refresh conflict when service authorization is revoked before confirmation', function (): void {
    [$owner, $tenant, $unit, , $accessToken] = reliabilityProposalContext();
    $payload = reliabilityProposalInput();
    $response = $this->withToken($accessToken)
        ->withHeader('X-Idempotency-Key', 'reliability-revoked-service-access')
        ->postJson('/api/v1/operations', $payload)
        ->assertStatus(202);
    $proposal = ProposedOperation::query()->findOrFail($response->json('data.id'));

    $servicePermission = Permission::query()->where('key', 'service.manage')->firstOrFail();
    RolePermission::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('permission_id', $servicePermission->getKey())
        ->delete();

    expect(fn () => app(ProposedOperationService::class)->confirm(
        $proposal,
        $owner,
        TenantContext::forUser($owner, (string) $tenant->getKey(), (string) $unit->getKey()),
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
    ))->toThrow(
        ConflictHttpException::class,
        'The proposal is no longer authorized in the current context. Refresh and create a new proposal.',
    );

    expect($proposal->fresh()->status)->toBe(ProposedOperation::STATUS_NEEDS_REFRESH);
});
