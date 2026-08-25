<?php

namespace App\Models;

use Database\Factories\SaleStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $sale_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $user_id
 * @property string|null $reason
 */
#[Fillable([
    'sale_id',
    'from_status',
    'to_status',
    'user_id',
    'reason',
])]
class SaleStatusHistory extends Model
{
    /** @use HasFactory<SaleStatusHistoryFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
