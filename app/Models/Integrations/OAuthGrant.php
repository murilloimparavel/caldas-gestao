<?php

namespace App\Models\Integrations;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Factories\Integrations\OAuthGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * @property string|null $auth_code_id
 * @property string|null $passport_token_id
 * @property string|null $passport_refresh_token_id
 * @property string $resource
 * @property list<string> $capabilities
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'id',
    'auth_code_id',
    'passport_token_id',
    'passport_refresh_token_id',
    'client_id',
    'user_id',
    'tenant_id',
    'unit_id',
    'resource',
    'capabilities',
    'expires_at',
    'last_used_at',
    'revoked_at',
])]
#[Hidden(['auth_code_id', 'passport_token_id', 'passport_refresh_token_id'])]
class OAuthGrant extends Model
{
    /** @use HasFactory<OAuthGrantFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $table = 'integration_oauth_grants';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Token, $this> */
    public function passportToken(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'passport_token_id');
    }

    /** @return BelongsTo<RefreshToken, $this> */
    public function passportRefreshToken(): BelongsTo
    {
        return $this->belongsTo(RefreshToken::class, 'passport_refresh_token_id');
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
