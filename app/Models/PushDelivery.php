<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $available_at
 * @property Carbon|null $sent_at
 * @property int $attempts
 */
#[Fillable(['agenda_notification_id', 'push_subscription_id', 'status', 'attempts', 'available_at', 'sent_at', 'last_error'])]
class PushDelivery extends Model
{
    use HasUuids;

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'available_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
