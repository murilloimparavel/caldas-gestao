<?php

use App\Actions\Identity\OnboardTenant;
use App\Models\Integrations\StepUpProof;
use App\Models\MembershipUnit;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Integrations\StepUpSession;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;

/** @return array{Request, Tenant, Unit} */
function integrationStepUpContext(User $user, ?Tenant $tenant = null, ?Unit $unit = null): array
{
    $tenant ??= (new OnboardTenant)->handle($user, [
        'name' => 'Step-up '.Str::random(8),
        'slug' => 'step-up-'.Str::lower(Str::random(8)),
    ]);
    $unit ??= $tenant->units()->firstOrFail();
    $request = Request::create('/integrations/step-up/credentials.issue');
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn (): User => $user);
    $request->session()->put('tenant_id', $tenant->getKey());
    $request->session()->put('unit_id', $unit->getKey());
    $request->attributes->set(TenantContext::class, TenantContext::fromRequest($request));

    return [$request, $tenant, $unit];
}

function integrationStepUpPasskey(User $user): Passkey
{
    return $user->passkeys()->create([
        'name' => 'Test authenticator',
        'credential_id' => (string) Str::uuid(),
        'credential' => [],
    ]);
}

it('does not treat login, password confirmation, or two-factor setup state as a step-up proof', function () {
    $user = User::factory()->create();
    [$request] = integrationStepUpContext($user);
    foreach ([
        'login_web_'.config('auth.defaults.guard') => 'user-id',
        'auth.password_confirmed_at' => now()->timestamp,
        'auth.two_factor_confirmed_at' => now()->timestamp,
        'two_factor.setup' => true,
    ] as $sessionKey => $value) {
        $request->session()->put($sessionKey, $value);

        expect(app(StepUpSession::class)->consumeProof($request, 'credentials.issue'))->toBeFalse();
    }
});

it('binds a challenge to one purpose, user, and five-minute window and consumes it once', function () {
    $user = User::factory()->create();
    [$request, $tenant] = integrationStepUpContext($user);
    $options = app(GenerateVerificationOptions::class)($user);
    $stepUp = app(StepUpSession::class);
    $id = $stepUp->issueChallenge($request, $options, 'credentials.issue');

    expect(fn () => $stepUp->assertPurpose('integrations.delete'))
        ->toThrow(ValidationException::class);
    expect(fn () => $stepUp->consumeChallenge($request, 'integrations.delete', $id))
        ->toThrow(ValidationException::class);

    $secondId = $stepUp->issueChallenge($request, $options, 'credentials.issue');
    $stepUp->consumeChallenge($request, 'credentials.issue', $secondId);
    expect(fn () => $stepUp->consumeChallenge($request, 'credentials.issue', $secondId))
        ->toThrow(ValidationException::class);

    $thirdId = $stepUp->issueChallenge($request, $options, 'credentials.issue');
    $otherUser = User::factory()->create();
    [$otherUserRequest] = integrationStepUpContext($otherUser);
    expect(fn () => $stepUp->consumeChallenge($otherUserRequest, 'credentials.issue', $thirdId))
        ->toThrow(ValidationException::class);

    $fourthId = $stepUp->issueChallenge($request, $options, 'credentials.issue');
    $otherTenant = (new OnboardTenant)->handle($user, [
        'name' => 'Step-up other '.Str::random(8),
        'slug' => 'step-up-other-'.Str::lower(Str::random(8)),
    ]);
    [$otherTenantRequest] = integrationStepUpContext($user, $otherTenant);
    expect(fn () => $stepUp->consumeChallenge($otherTenantRequest, 'credentials.issue', $fourthId))
        ->toThrow(ValidationException::class);

    $fifthId = $stepUp->issueChallenge($request, $options, 'credentials.issue');
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membership = $tenant->memberships()->where('user_id', $user->getKey())->firstOrFail();
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'is_primary' => false,
    ]);
    [$otherUnitRequest] = integrationStepUpContext($user, $tenant, $otherUnit);
    expect(fn () => $stepUp->consumeChallenge($otherUnitRequest, 'credentials.issue', $fifthId))
        ->toThrow(ValidationException::class);

    $malformedId = (string) Str::uuid();
    Cache::store('database')->put('integrations.step_up.challenge.'.$malformedId, [
        'options' => '{}',
        'user_id' => (string) $user->getKey(),
        'tenant_id' => (string) $tenant->getKey(),
        'purpose' => 'credentials.issue',
    ], now()->addMinute());
    expect(fn () => $stepUp->consumeChallenge($request, 'credentials.issue', $malformedId))
        ->toThrow(ValidationException::class);

    $expiredId = $stepUp->issueChallenge($request, $options, 'credentials.issue');
    $this->travel(301)->seconds();
    expect(fn () => $stepUp->consumeChallenge($request, 'credentials.issue', $expiredId))
        ->toThrow(ValidationException::class);
});

it('expires and consumes successful passkey proofs after one matching use', function () {
    $user = User::factory()->create();
    [$request, $tenant, $unit] = integrationStepUpContext($user);
    $stepUp = app(StepUpSession::class);
    $passkey = integrationStepUpPasskey($user);
    $stepUp->grant($request, 'credentials.issue', $passkey);

    $proofId = $request->session()->get('integrations.step_up.proof_id');
    expect($stepUp->consumeProof($request, 'credentials.revoke'))->toBeFalse();
    expect(StepUpProof::query()->findOrFail($proofId)->consumed_at)->toBeNull();

    $stepUp->grant($request, 'credentials.issue', $passkey);
    $staleRequestSnapshotProofId = $request->session()->get('integrations.step_up.proof_id');
    expect($staleRequestSnapshotProofId)->not->toBeEmpty();
    $this->assertDatabaseHas('step_up_proofs', [
        'id' => $staleRequestSnapshotProofId,
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'unit_id' => $unit->getKey(),
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => 'credentials.issue',
        'consumed_at' => null,
    ]);
    expect($stepUp->consumeProof($request, 'credentials.issue'))->toBeTrue();
    expect(StepUpProof::query()->findOrFail($staleRequestSnapshotProofId)->consumed_at)->not->toBeNull();
    $request->session()->put('integrations.step_up.proof_id', $staleRequestSnapshotProofId);
    expect($stepUp->consumeProof($request, 'credentials.issue'))->toBeFalse();

    $stepUp->grant($request, 'credentials.issue', $passkey);
    $otherTenant = (new OnboardTenant)->handle($user, [
        'name' => 'Step-up alternate '.Str::random(8),
        'slug' => 'step-up-alternate-'.Str::lower(Str::random(8)),
    ]);
    [$otherTenantRequest] = integrationStepUpContext($user, $otherTenant);
    $otherTenantRequest->session()->put('integrations.step_up.proof_id', $request->session()->get('integrations.step_up.proof_id'));
    expect($stepUp->consumeProof($otherTenantRequest, 'credentials.issue'))->toBeFalse();

    $stepUp->grant($request, 'credentials.issue', $passkey);
    $otherUnit = Unit::factory()->create(['tenant_id' => $tenant->getKey()]);
    $membership = $tenant->memberships()->where('user_id', $user->getKey())->firstOrFail();
    MembershipUnit::query()->create([
        'tenant_id' => $tenant->getKey(),
        'membership_id' => $membership->getKey(),
        'unit_id' => $otherUnit->getKey(),
        'is_primary' => false,
    ]);
    [$otherUnitRequest] = integrationStepUpContext($user, $tenant, $otherUnit);
    $otherUnitRequest->session()->put('integrations.step_up.proof_id', $request->session()->get('integrations.step_up.proof_id'));
    expect($stepUp->consumeProof($otherUnitRequest, 'credentials.issue'))->toBeFalse();

    $stepUp->grant($request, 'credentials.issue', $passkey);
    $expiredProofId = $request->session()->get('integrations.step_up.proof_id');
    $this->travel(301)->seconds();
    expect($stepUp->consumeProof($request, 'credentials.issue'))->toBeFalse();
    expect(StepUpProof::query()->findOrFail($expiredProofId)->consumed_at)->toBeNull();
});

it('invalidates a proof when its passkey has been deleted', function () {
    $user = User::factory()->create();
    [$request] = integrationStepUpContext($user);
    $stepUp = app(StepUpSession::class);
    $passkey = integrationStepUpPasskey($user);
    $proof = $stepUp->grant($request, 'credentials.issue', $passkey);

    $passkey->delete();

    expect($stepUp->consumeProof($request, 'credentials.issue'))->toBeFalse();
    expect($proof->refresh()->invalidated_at)->not->toBeNull();
});

it('records a persistent step-up only after the official passkey verification action succeeds', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    [, $tenant, $unit] = integrationStepUpContext($user);
    $passkey = integrationStepUpPasskey($user);
    Gate::define('manage-integrations', fn (): bool => true);

    $options = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->getKey(), 'unit_id' => $unit->getKey()])
        ->withoutMiddleware(['saas.access', 'first.login.complete'])
        ->getJson(route('integrations.step-up.options', 'credentials.issue'))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->json();

    $rawId = random_bytes(32);
    $credentialId = Base64UrlSafe::encodeUnpadded($rawId);
    $clientData = json_encode([
        'type' => 'webauthn.get',
        'challenge' => $options['options']['challenge'],
        'origin' => config('app.url'),
        'crossOrigin' => false,
    ], JSON_THROW_ON_ERROR);
    $credential = [
        'id' => $credentialId,
        'rawId' => $credentialId,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded(
                hash('sha256', 'localhost', true).chr(5).pack('N', 0),
            ),
            'signature' => Base64UrlSafe::encodeUnpadded(random_bytes(64)),
            'userHandle' => null,
        ],
    ];

    $verify = Mockery::mock(VerifyPasskey::class);
    $verify->shouldReceive('__invoke')
        ->once()
        ->withArgs(fn ($assertion, $requestOptions, $actualUser): bool => $actualUser->is($user)
            && $requestOptions->challenge === Base64UrlSafe::decodeNoPadding($options['options']['challenge']))
        ->andReturn($passkey);
    app()->instance(VerifyPasskey::class, $verify);

    $this->postJson(route('integrations.step-up.verify', 'credentials.issue'), [
        'ceremony' => $options['ceremony'],
        'credential' => $credential,
    ])->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Pragma', 'no-cache')
        ->assertJsonPath('status', 'passkey-step-up-verified');

    $proof = StepUpProof::query()->latest('created_at')->firstOrFail();
    expect($proof->user_id)->toBe($user->getKey())
        ->and($proof->tenant_id)->toBe($tenant->getKey())
        ->and($proof->unit_id)->toBe($unit->getKey())
        ->and($proof->factor)->toBe('passkey')
        ->and($proof->passkey_id)->toBe($passkey->getKey())
        ->and($proof->purpose)->toBe('credentials.issue')
        ->and($proof->consumed_at)->toBeNull();
});
