<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Events\PasskeyDeleted;
use ParagonIE\ConstantTime\Base64UrlSafe;

beforeEach(function () {
    if (! Features::enabled(Features::passkeys())) {
        $this->markTestSkipped('Fortify passkeys are not enabled.');
    }
});

it('starts a real passkey registration ceremony for the authenticated user', function () {
    $user = User::factory()->create(['email' => 'Owner@Example.com']);

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'));

    $response->assertSuccessful()
        ->assertJsonPath('options.rp.id', 'localhost')
        ->assertJsonPath('options.user.name', 'Owner@Example.com')
        ->assertJsonPath('options.user.displayName', $user->name)
        ->assertJsonPath('options.authenticatorSelection.userVerification', 'required')
        ->assertJsonPath('options.authenticatorSelection.residentKey', 'required');

    $this->assertNotEmpty(session('passkey.registration_options'));
});

it('starts a real discoverable passkey login ceremony for a guest', function () {
    $response = $this->getJson(route('passkey.login-options'));

    $response->assertSuccessful()
        ->assertJsonPath('options.rpId', 'localhost')
        ->assertJsonPath('options.userVerification', 'required')
        ->assertJsonPath('options.allowCredentials', []);

    $this->assertNotEmpty(session('passkey.verification_options'));
});

it('completes the passkey login HTTP pipeline with a structured credential fixture', function () {
    $user = User::factory()->create();
    $rawId = random_bytes(32);
    $passkey = $user->passkeys()->create([
        'name' => 'Platform authenticator',
        'credential_id' => Base64UrlSafe::encodeUnpadded($rawId),
        'credential' => [],
    ]);

    $options = $this->getJson(route('passkey.login-options'))
        ->assertSuccessful()
        ->json('options');

    $verify = Mockery::mock(VerifyPasskey::class);
    $verify->shouldReceive('__invoke')->once()->andReturn($passkey);
    app()->instance(VerifyPasskey::class, $verify);

    $clientData = json_encode([
        'type' => 'webauthn.get',
        'challenge' => $options['challenge'],
        'origin' => config('app.url'),
        'crossOrigin' => false,
    ], JSON_THROW_ON_ERROR);

    $credentialId = Base64UrlSafe::encodeUnpadded($rawId);

    $this->postJson(route('passkey.login'), [
        'credential' => [
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
        ],
    ])->assertSuccessful()
        ->assertJsonPath('redirect', route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('removes an owned passkey through the Fortify endpoint', function () {
    $user = User::factory()->create();
    $passkey = $user->passkeys()->create([
        'name' => 'Platform authenticator',
        'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
        'credential' => [],
    ]);
    Event::fake([PasskeyDeleted::class]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->deleteJson(route('passkey.destroy', $passkey))
        ->assertSuccessful()
        ->assertJsonPath('status', 'passkey-deleted');

    $this->assertModelMissing($passkey);
    Event::assertDispatched(PasskeyDeleted::class);
});

it('forbids removing another users passkey', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $passkey = $owner->passkeys()->create([
        'name' => 'Platform authenticator',
        'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
        'credential' => [],
    ]);

    $this->actingAs($otherUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->deleteJson(route('passkey.destroy', $passkey))
        ->assertForbidden();

    $this->assertModelExists($passkey);
});
