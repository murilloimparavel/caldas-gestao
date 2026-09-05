<?php

namespace App\Models;

use App\Policies\CustomerCommunicationPreferencePolicy;
use Database\Factories\CustomerCommunicationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'unit_id', 'customer_id', 'channel', 'opted_in', 'source', 'consented_at', 'revoked_at', 'lock_version'])]
#[UsePolicy(CustomerCommunicationPreferencePolicy::class)]
class CustomerCommunicationPreference extends Model
{
    /** @use HasFactory<CustomerCommunicationPreferenceFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['opted_in' => 'boolean', 'consented_at' => 'datetime', 'revoked_at' => 'datetime', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
