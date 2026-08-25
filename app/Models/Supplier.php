<?php

namespace App\Models;

use App\Policies\SupplierPolicy;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $unit_id
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $document_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property bool $is_active
 * @property int $lock_version
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'name',
    'trade_name',
    'document_number',
    'email',
    'phone',
    'notes',
    'is_active',
    'lock_version',
])]
#[UsePolicy(SupplierPolicy::class)]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
}
