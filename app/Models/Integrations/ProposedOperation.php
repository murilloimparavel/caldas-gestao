<?php

namespace App\Models\Integrations;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string|null $credential_id
 * @property string|null $oauth_grant_id
 * @property string $source
 * @property string $actor_id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $operation_key
 * @property array<string, mixed> $input
 * @property string $input_hash
 * @property string|null $request_hash
 * @property string $idempotency_key_hash
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $executed_at
 * @property array{resource_id: string, resource_type: string}|null $result_reference
 */
#[Fillable([
    'id', 'credential_id', 'oauth_grant_id', 'source', 'actor_id', 'tenant_id', 'unit_id', 'operation_key', 'input', 'input_hash', 'request_hash',
    'idempotency_key_hash', 'status', 'expires_at', 'confirmed_at', 'rejected_at', 'executed_at', 'result_reference',
])]
class ProposedOperation extends Model
{
    public const string SOURCE_CREDENTIAL = 'credential';

    public const string SOURCE_OAUTH = 'oauth';

    public const string SOURCE_INTERNAL_ASSISTANT = 'internal_assistant';

    public const string STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    public const string STATUS_EXECUTING = 'executing';

    public const string STATUS_SUCCEEDED = 'succeeded';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_NEEDS_REFRESH = 'needs_refresh';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_EXPIRED = 'expired';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result_reference' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $operation): void {
            foreach (['credential_id', 'oauth_grant_id', 'source', 'actor_id', 'tenant_id', 'unit_id', 'operation_key', 'input', 'input_hash', 'request_hash', 'idempotency_key_hash', 'expires_at'] as $attribute) {
                if ($operation->isDirty($attribute)) {
                    throw new LogicException('Proposed operation command data is immutable.');
                }
            }
        });
    }

    public function markRejected(): void
    {
        $this->transition(self::STATUS_REJECTED, 'rejected_at', self::STATUS_PENDING_CONFIRMATION);
    }

    public function markExpired(): void
    {
        $this->transition(self::STATUS_EXPIRED, null, self::STATUS_PENDING_CONFIRMATION);
    }

    public function markNeedsRefresh(): void
    {
        $this->transition(self::STATUS_NEEDS_REFRESH, null, [self::STATUS_PENDING_CONFIRMATION, self::STATUS_EXECUTING]);
    }

    public function markExecuting(): void
    {
        $this->transition(self::STATUS_EXECUTING, null, self::STATUS_PENDING_CONFIRMATION);
    }

    /** @param array{resource_id: string, resource_type: string} $reference */
    public function markSucceeded(array $reference): void
    {
        if ($this->status !== self::STATUS_EXECUTING) {
            throw new LogicException('Only an executing proposed operation may succeed.');
        }

        $this->status = self::STATUS_SUCCEEDED;
        $this->confirmed_at = Carbon::now();
        $this->executed_at = Carbon::now();
        $this->result_reference = $reference;
        $this->save();
    }

    public function markFailed(): void
    {
        $this->transition(self::STATUS_FAILED, null, [self::STATUS_PENDING_CONFIRMATION, self::STATUS_EXECUTING]);
    }

    /** @return BelongsTo<IntegrationCredential, $this> */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(IntegrationCredential::class, 'credential_id');
    }

    /** @return BelongsTo<OAuthGrant, $this> */
    public function oauthGrant(): BelongsTo
    {
        return $this->belongsTo(OAuthGrant::class, 'oauth_grant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @param string|list<string> $fromStatus */
    private function transition(string $status, ?string $timestampAttribute, string|array $fromStatus): void
    {
        if (is_array($fromStatus) ? ! in_array($this->status, $fromStatus, true) : $this->status !== $fromStatus) {
            throw new LogicException('The proposed operation is in an invalid state for this transition.');
        }

        $this->status = $status;
        if ($timestampAttribute !== null) {
            $this->{$timestampAttribute} = now();
        }
        $this->save();
    }
}
