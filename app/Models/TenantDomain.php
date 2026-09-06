<?php

namespace App\Models;

use App\Enums\TenantDomainKind;
use App\Enums\TenantDomainStatus;
use Database\Factories\TenantDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'hostname', 'kind', 'status', 'verification_token', 'expected_cname', 'verified_at', 'activated_at', 'disabled_at', 'ssl_status', 'ssl_verified_at', 'last_dns_check_at', 'last_dns_error', 'metadata'])]
class TenantDomain extends Model
{
    /** @use HasFactory<TenantDomainFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'kind' => TenantDomainKind::class,
            'status' => TenantDomainStatus::class,
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
            'ssl_verified_at' => 'datetime',
            'last_dns_check_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return Attribute<string, string> */
    protected function hostname(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => strtolower(trim($value)));
    }
}
