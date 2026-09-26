<?php

namespace App\Models;

use Database\Factories\GoogleCalendarConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string> $scopes
 * @property Carbon|null $token_expires_at
 * @property Carbon|null $last_synced_at
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'unit_id', 'connected_by_user_id', 'provider', 'status', 'google_account_email', 'calendar_id', 'calendar_name', 'access_token', 'refresh_token', 'scopes', 'token_expires_at', 'last_error', 'last_synced_at', 'lock_version'])]
#[Hidden(['access_token', 'refresh_token'])]
class GoogleCalendarConnection extends Model
{
    /** @use HasFactory<GoogleCalendarConnectionFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'provider' => 'google',
        'status' => 'disconnected',
        'scopes' => '[]',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes' => 'array',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'lock_version' => 'integer',
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
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }
}
