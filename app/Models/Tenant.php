<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TenantStatus $status
 * @property int $lock_version
 */
#[Fillable(['slug', 'name', 'brand_name', 'logo_url', 'favicon_url', 'primary_color', 'accent_color', 'legal_name', 'status', 'timezone', 'default_currency'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => TenantStatus::Active->value,
        'timezone' => 'UTC',
        'default_currency' => 'BRL',
        'lock_version' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'lock_version' => 'integer',
        ];
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
