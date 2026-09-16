<?php

namespace App\Models;

use App\Policies\CommissionPolicy;
use Database\Factories\CommissionRuleFactory;
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
 * @property string|null $professional_id
 * @property string|null $service_id
 * @property string|null $product_id
 * @property string|null $category_id
 * @property string $scope
 * @property string $type
 * @property int $value_rate
 * @property bool $is_active
 * @property int $lock_version
 */
#[Fillable(['tenant_id', 'unit_id', 'professional_id', 'service_id', 'product_id', 'category_id', 'scope', 'type', 'value_rate', 'is_active', 'lock_version'])]
#[UsePolicy(CommissionPolicy::class)]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'type' => 'percentage',
        'value_rate' => 0,
        'is_active' => true,
        'lock_version' => 0,
        'scope' => 'all',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'value_rate' => 'integer',
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

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
