<?php

namespace App\Support\Integrations;

use App\Models\Integrations\OAuthGrant;
use App\Models\Integrations\ProposedOperation;
use App\Models\Integrations\StepUpProof;
use App\Support\TenantContext;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialRequestOptions;

final class StepUpSession
{
    public const int LIFETIME_SECONDS = 300;

    /** @var list<string> */
    public const array PURPOSES = [
        'credentials.issue',
        'credentials.revoke',
        'oauth.consent',
        'oauth.revoke',
        'operations.confirm',
    ];

    private const string CACHE_PREFIX = 'integrations.step_up.';

    private const string PROOF_SESSION_KEY = 'integrations.step_up.proof_id';

    private const int LOCK_WAIT_SECONDS = 3;

    public function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw ValidationException::withMessages([
                'purpose' => __('Unsupported step-up purpose.'),
            ]);
        }
    }

    public function issueChallenge(Request $request, PublicKeyCredentialRequestOptions $options, string $purpose): string
    {
        $this->assertPurpose($purpose);
        $id = (string) Str::uuid();
        $context = $this->context($request);
        $binding = $this->binding($request, $purpose);

        Cache::store('database')->put($this->challengeKey($id), [
            'options' => WebAuthn::toJson($options),
            'user_id' => (string) $request->user()->getAuthIdentifier(),
            'tenant_id' => (string) $context->tenant->getKey(),
            'unit_id' => $context->unit === null ? null : (string) $context->unit->getKey(),
            'purpose' => $purpose,
            ...$binding,
        ], now()->addSeconds(self::LIFETIME_SECONDS));

        return $id;
    }

    public function consumeChallenge(Request $request, string $purpose, string $id): PublicKeyCredentialRequestOptions
    {
        $this->assertPurpose($purpose);

        $challenge = $this->consumeCacheValue($this->challengeKey($id));
        $context = $this->context($request);
        $binding = $this->binding($request, $purpose);

        if (
            ! is_array($challenge)
            || ! is_string($challenge['options'] ?? null)
            || ! is_string($challenge['user_id'] ?? null)
            || ! is_string($challenge['tenant_id'] ?? null)
            || ! is_string($challenge['purpose'] ?? null)
            || ($challenge['target_id'] ?? null) !== $binding['target_id']
            || ($challenge['command_hash'] ?? null) !== $binding['command_hash']
            || ! array_key_exists('unit_id', $challenge)
            || (! is_null($challenge['unit_id']) && ! is_string($challenge['unit_id']))
            || $challenge['user_id'] !== (string) $request->user()->getAuthIdentifier()
            || $challenge['tenant_id'] !== (string) $context->tenant->getKey()
            || $challenge['unit_id'] !== ($context->unit === null ? null : (string) $context->unit->getKey())
            || $challenge['purpose'] !== $purpose
        ) {
            throw ValidationException::withMessages([
                'credential' => __('The passkey challenge is invalid, expired, or belongs to a different context.'),
            ]);
        }

        return WebAuthn::fromJson($challenge['options'], PublicKeyCredentialRequestOptions::class);
    }

    public function grant(Request $request, string $purpose, Passkey $passkey): StepUpProof
    {
        $this->assertPurpose($purpose);
        $context = $this->context($request);
        $binding = $this->binding($request, $purpose);
        $proof = DB::transaction(fn (): StepUpProof => StepUpProof::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) $request->user()->getAuthIdentifier(),
            'tenant_id' => (string) $context->tenant->getKey(),
            'unit_id' => $context->unit === null ? null : (string) $context->unit->getKey(),
            'passkey_id' => $passkey->getKey(),
            'factor' => 'passkey',
            'purpose' => $purpose,
            ...$binding,
            'verified_at' => now(),
            'expires_at' => now()->addSeconds(self::LIFETIME_SECONDS),
        ]));

        $request->session()->put(self::PROOF_SESSION_KEY, $proof->getKey());

        return $proof;
    }

    public function consumeProof(Request $request, string $purpose, ?ProposedOperation $proposal = null): bool
    {
        $this->assertPurpose($purpose);
        if ($purpose === 'operations.confirm' && ! $proposal instanceof ProposedOperation) {
            $request->session()->forget(self::PROOF_SESSION_KEY);

            return false;
        }
        $id = $request->session()->get(self::PROOF_SESSION_KEY);

        if (! is_string($id) || $id === '') {
            return false;
        }

        $context = $this->context($request);
        $binding = $purpose === 'operations.confirm'
            ? $this->bindingForProposal($purpose, $proposal)
            : $this->binding($request, $purpose);
        $request->session()->forget(self::PROOF_SESSION_KEY);

        return DB::transaction(function () use ($id, $request, $context, $purpose, $binding): bool {
            $userId = (string) $request->user()->getAuthIdentifier();
            $timestamp = now();
            $consumed = StepUpProof::query()
                ->whereKey($id)
                ->where('user_id', $userId)
                ->where('tenant_id', (string) $context->tenant->getKey())
                ->where('unit_id', $context->unit === null ? null : (string) $context->unit->getKey())
                ->where('factor', 'passkey')
                ->where('purpose', $purpose)
                ->where('target_id', $binding['target_id'])
                ->where('command_hash', $binding['command_hash'])
                ->where('verified_at', '<=', $timestamp)
                ->where('expires_at', '>', $timestamp)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->whereIn('passkey_id', Passkey::query()->select('id')->where('user_id', $userId))
                ->update(['consumed_at' => $timestamp, 'updated_at' => $timestamp]);

            if ($consumed === 1) {
                return true;
            }

            StepUpProof::query()
                ->whereKey($id)
                ->where('user_id', $userId)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update(['invalidated_at' => $timestamp, 'updated_at' => $timestamp]);

            return false;
        });
    }

    /** @return array{target_id: string|null, command_hash: string|null} */
    private function binding(Request $request, string $purpose): array
    {
        if ($purpose === 'oauth.revoke') {
            $context = $this->context($request);
            $grantId = $request->route('grant') ?? $request->input('oauth_grant_id', $request->query('oauth_grant_id'));
            $grant = is_string($grantId)
                ? OAuthGrant::query()
                    ->whereKey($grantId)
                    ->where('user_id', $context->user->getKey())
                    ->where('tenant_id', $context->tenant->getKey())
                    ->where('unit_id', $context->unit?->getKey())
                    ->whereNull('revoked_at')
                    ->first()
                : null;

            if (! $grant instanceof OAuthGrant) {
                throw ValidationException::withMessages([
                    'oauth_grant_id' => __('A valid active OAuth grant is required.'),
                ]);
            }

            $capabilities = $grant->capabilities;
            sort($capabilities, SORT_STRING);

            return [
                'target_id' => (string) $grant->getKey(),
                'command_hash' => hash('sha256', $grant->client_id."\0".$grant->resource."\0".json_encode($capabilities, JSON_THROW_ON_ERROR)),
            ];
        }

        if ($purpose === 'oauth.consent') {
            $clientId = $request->input('client_id', $request->query('client_id'));
            $capabilities = $request->input('capabilities', $request->query('capabilities'));
            $context = $this->context($request);
            $authorization = $request->session()->get('integration.oauth.context');

            if (! is_array($authorization)
                || ($authorization['client_id'] ?? null) !== $clientId
                || ($authorization['user_id'] ?? null) !== (string) $request->user()->getAuthIdentifier()
                || ($authorization['tenant_id'] ?? null) !== (string) $context->tenant->getKey()
                || ($authorization['unit_id'] ?? null) !== ($context->unit === null ? null : (string) $context->unit->getKey())
                || ! is_string($clientId)
                || trim($clientId) === ''
                || mb_strlen($clientId) > 255
                || ! is_array($capabilities)
                || $capabilities === []) {
                throw ValidationException::withMessages([
                    'consent' => __('The OAuth consent details are invalid.'),
                ]);
            }

            foreach ($capabilities as $capability) {
                if (! is_string($capability) || trim($capability) === '' || mb_strlen($capability) > 100) {
                    throw ValidationException::withMessages([
                        'consent' => __('The OAuth consent details are invalid.'),
                    ]);
                }
            }

            if (count($capabilities) !== count(array_unique($capabilities))) {
                throw ValidationException::withMessages([
                    'consent' => __('The OAuth consent details are invalid.'),
                ]);
            }

            sort($capabilities, SORT_STRING);

            return [
                'target_id' => trim($clientId),
                'command_hash' => hash('sha256', json_encode($capabilities, JSON_THROW_ON_ERROR)),
            ];
        }

        if ($purpose === 'operations.confirm') {
            $id = $request->input('proposal', $request->query('proposal'));
            $proposal = is_string($id) ? ProposedOperation::query()->find($id) : null;
            $context = $this->context($request);

            if (! $proposal instanceof ProposedOperation
                || $proposal->actor_id !== (string) $request->user()->getAuthIdentifier()
                || $proposal->tenant_id !== (string) $context->tenant->getKey()
                || $proposal->unit_id !== ($context->unit === null ? '' : (string) $context->unit->getKey())
                || $proposal->status !== ProposedOperation::STATUS_PENDING_CONFIRMATION
                || $proposal->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'proposal' => __('A valid pending proposal is required for this passkey confirmation.'),
                ]);
            }

            return $this->bindingForProposal($purpose, $proposal);
        }

        return ['target_id' => null, 'command_hash' => null];
    }

    /** @return array{target_id: string|null, command_hash: string|null} */
    private function bindingForProposal(string $purpose, ?ProposedOperation $proposal): array
    {
        if ($purpose !== 'operations.confirm') {
            return ['target_id' => null, 'command_hash' => null];
        }
        if (! $proposal instanceof ProposedOperation) {
            return ['target_id' => null, 'command_hash' => null];
        }

        return [
            'target_id' => (string) $proposal->getKey(),
            'command_hash' => hash('sha256', $proposal->operation_key."\0".$proposal->input_hash),
        ];
    }

    private function consumeCacheValue(string $key): mixed
    {
        try {
            $store = Cache::store('database')->getStore();

            if (! $store instanceof DatabaseStore) {
                throw new \LogicException('The step-up challenge cache must use the database store.');
            }

            return $store->lock($key.':consume', self::LIFETIME_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): mixed => Cache::store('database')->pull($key));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    private function challengeKey(string $id): string
    {
        return self::CACHE_PREFIX.'challenge.'.$id;
    }

    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        if ($context instanceof TenantContext && $context->user->is($request->user())) {
            return $context;
        }

        return TenantContext::fromRequest($request);
    }
}
