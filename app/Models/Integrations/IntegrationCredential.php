<?php

namespace App\Models\Integrations;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Passport\Token;

/**
 * @property list<string> $capabilities
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
#[Fillable(['id', 'passport_token_id', 'user_id', 'tenant_id', 'unit_id', 'label', 'capabilities', 'expires_at', 'revoked_at'])]
class IntegrationCredential extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['passport_token_id'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Token, $this> */
    public function passportToken(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'passport_token_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
