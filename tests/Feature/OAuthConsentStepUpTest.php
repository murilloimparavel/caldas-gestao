<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Middleware\RequirePasskeyStepUp;
use App\Models\Integrations\StepUpProof;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\Integrations\StepUpSession;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passport\Passport;
use ParagonIE\ConstantTime\Base64UrlSafe;

/** @return array{User, TenantContext, Request, string} */
function oauthConsentStepUpFixture(array $capabilities = ['context:read']): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($user, [
        'name' => 'OAuth consent step-up '.Str::random(8),
        'slug' => 'oauth-consent-step-up-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    $context = TenantContext::forUser($user, (string) $tenant->getKey(), (string) $unit->getKey());
    $clientId = (string) Str::uuid();
    $request = Request::create('/integrations/step-up/oauth.consent/options', 'GET');
    $request->setUserResolver(fn (): User => $user);
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put([
        'tenant_id' => (string) $tenant->getKey(),
        'unit_id' => (string) $unit->getKey(),
        'integration.oauth.context' => [
            'user_id' => (string) $user->getKey(),
            'client_id' => $clientId,
            'tenant_id' => (string) $tenant->getKey(),
            'unit_id' => (string) $unit->getKey(),
            'resource' => 'https://app.example.test/mcp/integration',
            'capabilities' => $capabilities,
        ],
    ]);
    $request->attributes->set(TenantContext::class, $context);

    return [$user, $context, $request, $clientId];
}

function grantOAuthConsentStepUp(Request $request): StepUpProof
{
    $user = $request->user();
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'OAuth consent test passkey',
        'credential_id' => (string) Str::uuid(),
        'credential' => ['credentialId' => 'oauth-consent-step-up'],
    ]);

    return app(StepUpSession::class)->grant($request, 'oauth.consent', $passkey);
}

it('rejects OAuth consent when no recent passkey proof exists', function (): void {
    [$user, $context, $request, $clientId] = oauthConsentStepUpFixture();
    $request->merge(['client_id' => $clientId, 'capabilities' => ['context:read']]);

    expect(fn () => app(RequirePasskeyStepUp::class)->handle(
        $request,
        fn () => response()->noContent(),
        'oauth.consent',
    ))->toThrow(AuthorizationException::class);
});

it('accepts a fresh OAuth consent proof only once for its client, tenant, unit, and capabilities', function (): void {
    [, , $request, $clientId] = oauthConsentStepUpFixture(['catalog:read', 'context:read']);
    $request->query->add([
        'client_id' => $clientId,
        'capabilities' => ['context:read', 'catalog:read'],
    ]);
    $proof = grantOAuthConsentStepUp($request);

    expect($request->input('client_id'))->toBe($clientId)
        ->and($request->input('capabilities'))->toBe(['context:read', 'catalog:read'])
        ->and($request->session()->get('integrations.step_up.proof_id'))->toBe((string) $proof->getKey())
        ->and($proof->target_id)->toBe($clientId);

    $response = app(RequirePasskeyStepUp::class)->handle(
        $request,
        fn () => response()->noContent(),
        'oauth.consent',
    );

    expect($response->getStatusCode())->toBe(204)
        ->and($proof->fresh()->consumed_at)->not->toBeNull();

    $request->session()->put('integrations.step_up.proof_id', (string) $proof->getKey());
    expect(fn () => app(RequirePasskeyStepUp::class)->handle(
        $request,
        fn () => response()->noContent(),
        'oauth.consent',
    ))->toThrow(AuthorizationException::class);
});

it('invalidates the OAuth consent proof when the selected capabilities change', function (): void {
    [, , $request, $clientId] = oauthConsentStepUpFixture(['context:read']);
    $request->query->add(['client_id' => $clientId, 'capabilities' => ['context:read']]);
    $proof = grantOAuthConsentStepUp($request);
    $request->query->set('capabilities', ['catalog:read']);

    expect(fn () => app(RequirePasskeyStepUp::class)->handle(
        $request,
        fn () => response()->noContent(),
        'oauth.consent',
    ))->toThrow(AuthorizationException::class);

    expect($proof->fresh()->consumed_at)->toBeNull()
        ->and($proof->fresh()->invalidated_at)->not->toBeNull();
});

it('creates the consent proof through the authenticated step-up endpoints using the OAuth tenant context', function (): void {
    [$user, $context, , $clientId] = oauthConsentStepUpFixture(['catalog:read', 'context:read']);
    $otherTenant = (new OnboardTenant)->handle($user, [
        'name' => 'Other OAuth context '.Str::random(8),
        'slug' => 'other-oauth-context-'.Str::lower(Str::random(8)),
    ]);
    $otherUnit = $otherTenant->units()->firstOrFail();
    $passkey = $user->passkeys()->create([
        'name' => 'OAuth consent test key',
        'credential_id' => (string) Str::uuid(),
        'credential' => [],
    ]);
    $authorization = [
        'user_id' => (string) $user->getKey(),
        'client_id' => $clientId,
        'tenant_id' => (string) $context->tenant->getKey(),
        'unit_id' => (string) $context->unit->getKey(),
        'resource' => 'https://app.example.test/mcp/integration',
        'capabilities' => ['catalog:read', 'context:read'],
    ];
    $options = $this->actingAs($user)
        ->withSession([
            'tenant_id' => (string) $otherTenant->getKey(),
            'unit_id' => (string) $otherUnit->getKey(),
            'integration.oauth.context' => $authorization,
        ])
        ->getJson(route('integrations.step-up.options', 'oauth.consent').'?'.http_build_query([
            'client_id' => $clientId,
            'capabilities' => ['context:read', 'catalog:read'],
        ]))
        ->assertSuccessful()
        ->json();
    $clientData = json_encode([
        'type' => 'webauthn.get',
        'challenge' => $options['options']['challenge'],
        'origin' => config('app.url'),
        'crossOrigin' => false,
    ], JSON_THROW_ON_ERROR);
    $credentialId = Base64UrlSafe::encodeUnpadded(random_bytes(32));
    $credential = [
        'id' => $credentialId,
        'rawId' => $credentialId,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
            'authenticatorData' => Base64UrlSafe::encodeUnpadded(hash('sha256', 'localhost', true).chr(5).pack('N', 0)),
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

    $this->postJson(route('integrations.step-up.verify', 'oauth.consent'), [
        'client_id' => $clientId,
        'capabilities' => ['context:read', 'catalog:read'],
        'ceremony' => $options['ceremony'],
        'credential' => $credential,
    ])->assertSuccessful()->assertJsonPath('status', 'passkey-step-up-verified');

    $proof = StepUpProof::query()->latest('created_at')->firstOrFail();
    expect($proof->user_id)->toBe($user->getKey())
        ->and($proof->tenant_id)->toBe((string) $context->tenant->getKey())
        ->and($proof->unit_id)->toBe((string) $context->unit->getKey())
        ->and($proof->purpose)->toBe('oauth.consent')
        ->and($proof->target_id)->toBe($clientId)
        ->and($proof->command_hash)->toBe(hash('sha256', json_encode(['catalog:read', 'context:read'], JSON_THROW_ON_ERROR)))
        ->and($proof->passkey_id)->toBe($passkey->getKey());
});

it('binds OAuth consent proofs to the client id and disables Passport device authorization', function (): void {
    [, , $request, $clientId] = oauthConsentStepUpFixture();
    $request->query->add(['client_id' => $clientId, 'capabilities' => ['context:read']]);
    $proof = grantOAuthConsentStepUp($request);
    $request->query->set('client_id', 'another-client');

    expect(fn () => app(RequirePasskeyStepUp::class)->handle(
        $request,
        fn () => response()->noContent(),
        'oauth.consent',
    ))->toThrow(ValidationException::class);

    expect($proof->target_id)->toBe($clientId)
        ->and($proof->fresh()->consumed_at)->toBeNull()
        ->and(Passport::$deviceCodeGrantEnabled)->toBeFalse()
        ->and(Route::has('passport.device'))->toBeFalse()
        ->and(Route::has('passport.device.code'))->toBeFalse()
        ->and(Route::has('passport.device.authorizations.authorize'))->toBeFalse()
        ->and(Route::getRoutes()->getByName('passport.authorizations.approve')?->gatherMiddleware())
        ->toContain(RequirePasskeyStepUp::class.':oauth.consent');
});
