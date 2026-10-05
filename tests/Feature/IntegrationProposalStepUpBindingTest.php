<?php

use App\Actions\Identity\OnboardTenant;
use App\Http\Middleware\RequirePasskeyStepUp;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;

/** @return array{User, Tenant, Unit, Passkey} */
function boundProposalStepUpContext(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'first_login_at' => now(),
        'must_change_password' => false,
    ]);
    $tenant = (new OnboardTenant)->handle($user, [
        'name' => 'Step-up binding '.Str::random(8),
        'slug' => 'step-up-binding-'.Str::lower(Str::random(8)),
    ]);
    $unit = $tenant->units()->firstOrFail();
    TenantSubscription::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'status' => 'active',
        'ends_at' => now()->addDays(30),
    ]);
    $passkey = Passkey::query()->forceCreate([
        'user_id' => $user->getKey(),
        'name' => 'Proposal confirmation key',
        'credential_id' => 'proposal-binding-'.Str::uuid(),
        'credential' => ['credentialId' => 'proposal-binding'],
    ]);

    return [$user, $tenant, $unit, $passkey];
}

function boundProposal(string $id, User $user, string $tenantId, string $unitId, string $inputHash): ProposedOperation
{
    return new ProposedOperation([
        'id' => $id,
        'actor_id' => (string) $user->getKey(),
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'operation_key' => 'service.create',
        'input_hash' => $inputHash,
        'status' => ProposedOperation::STATUS_PENDING_CONFIRMATION,
        'expires_at' => now()->addMinutes(5),
    ]);
}

function boundProposalProof(User $user, string $tenantId, string $unitId, Passkey $passkey, ProposedOperation $proposal): StepUpProof
{
    return StepUpProof::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->getKey(),
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'passkey_id' => $passkey->getKey(),
        'factor' => 'passkey',
        'purpose' => 'operations.confirm',
        'target_id' => (string) $proposal->getKey(),
        'command_hash' => hash('sha256', $proposal->operation_key."\0".$proposal->input_hash),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);
}

function proposalConfirmationRequest(User $user, string $tenantId, string $unitId, ProposedOperation $proposal, string $proofId): Request
{
    $request = Request::create('/settings/integrations/proposals/'.$proposal->getKey().'/confirm', 'POST');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['POST'], '/settings/integrations/proposals/{proposal}/confirm', fn () => null);
    $route->bind($request);
    $route->setParameter('proposal', $proposal);
    $request->setRouteResolver(function () use ($route): Route {
        return $route;
    });
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put([
        'tenant_id' => $tenantId,
        'unit_id' => $unitId,
        'integrations.step_up.proof_id' => $proofId,
    ]);

    return $request;
}

it('rejects a passkey proof bound to another proposal in the same unit', function (): void {
    [$user, $tenant, $unit, $passkey] = boundProposalStepUpContext();
    $reviewed = boundProposal((string) Str::uuid(), $user, (string) $tenant->getKey(), (string) $unit->getKey(), str_repeat('a', 64));
    $target = boundProposal((string) Str::uuid(), $user, (string) $tenant->getKey(), (string) $unit->getKey(), str_repeat('b', 64));
    $proof = boundProposalProof($user, (string) $tenant->getKey(), (string) $unit->getKey(), $passkey, $reviewed);
    $request = proposalConfirmationRequest($user, (string) $tenant->getKey(), (string) $unit->getKey(), $target, (string) $proof->getKey());

    expect(fn () => app(RequirePasskeyStepUp::class)->handle($request, fn () => response()->noContent(), 'operations.confirm'))
        ->toThrow(AuthorizationException::class);
    expect($proof->fresh()->consumed_at)->toBeNull()
        ->and($proof->fresh()->invalidated_at)->not->toBeNull();
});

it('accepts a proof only for the proposal and immutable command hash it reviewed', function (): void {
    [$user, $tenant, $unit, $passkey] = boundProposalStepUpContext();
    $proposal = boundProposal((string) Str::uuid(), $user, (string) $tenant->getKey(), (string) $unit->getKey(), str_repeat('c', 64));
    $proof = boundProposalProof($user, (string) $tenant->getKey(), (string) $unit->getKey(), $passkey, $proposal);
    $request = proposalConfirmationRequest($user, (string) $tenant->getKey(), (string) $unit->getKey(), $proposal, (string) $proof->getKey());

    $response = app(RequirePasskeyStepUp::class)->handle($request, fn () => response()->noContent(), 'operations.confirm');

    expect($response->getStatusCode())->toBe(204)
        ->and($proof->fresh()->consumed_at)->not->toBeNull();
});
