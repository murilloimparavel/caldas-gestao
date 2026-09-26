<?php

namespace App\Models;

use Database\Factories\GoogleCalendarOAuthStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable(['tenant_id', 'unit_id', 'user_id', 'state_hash', 'code_verifier', 'redirect_uri', 'return_host', 'return_path', 'expires_at', 'consumed_at'])]
#[Hidden(['state_hash', 'code_verifier'])]
class GoogleCalendarOAuthState extends Model
{
    /** @use HasFactory<GoogleCalendarOAuthStateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'code_verifier' => 'encrypted',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
