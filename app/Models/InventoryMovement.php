<?php

namespace App\Models;

use App\Policies\InventoryPolicy;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $product_id
 * @property string $type
 * @property int $quantity
 * @property int $unit_cost_cents
 * @property int $previous_stock
 * @property int $resulting_stock
 * @property string $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string $user_id
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'product_id',
    'type',
    'quantity',
    'unit_cost_cents',
    'previous_stock',
    'resulting_stock',
    'reason',
    'reference_type',
    'reference_id',
    'user_id',
])]
#[UsePolicy(InventoryPolicy::class)]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'unit_cost_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_cents' => 'integer',
            'previous_stock' => 'integer',
            'resulting_stock' => 'integer',
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
