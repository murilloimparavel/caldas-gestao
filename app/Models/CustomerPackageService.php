<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'unit_id', 'customer_package_id', 'service_id',
    'allocated_quantity', 'remaining_quantity',
])]
class CustomerPackageService extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected function casts(): array
    {
        return [
            'allocated_quantity' => 'integer',
            'remaining_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<CustomerPackage, $this> */
    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
