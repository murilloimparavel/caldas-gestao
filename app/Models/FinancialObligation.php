<?php

namespace App\Models;

use App\Policies\FinancialObligationPolicy;
use Database\Factories\FinancialObligationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $unit_id
 * @property string $type
 * @property string|null $category_id
 * @property string|null $supplier_id
 * @property string|null $customer_id
 * @property string $description
 * @property int $amount_cents
 * @property Carbon $due_date
 * @property Carbon|null $paid_date
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $notes
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Unit $unit
 * @property-read Category|null $category
 * @property-read Supplier|null $supplier
 * @property-read Customer|null $customer
 */
#[Fillable([
    'tenant_id',
    'unit_id',
    'type',
    'category_id',
    'supplier_id',
    'customer_id',
    'description',
    'amount_cents',
    'due_date',
    'paid_date',
    'status',
    'payment_method',
    'notes',
    'lock_version',
])]
#[UsePolicy(FinancialObligationPolicy::class)]
class FinancialObligation extends Model
{
    /** @use HasFactory<FinancialObligationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
        'lock_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
            'amount_cents' => 'integer',
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
