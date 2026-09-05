<?php

namespace App\Models;

use Database\Factories\SubscriptionRenewalAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'unit_id', 'customer_subscription_id', 'source_cycle_id', 'target_cycle_id', 'idempotency_key', 'status', 'failure_code', 'failure_message', 'attempted_at', 'completed_at', 'metadata'])]
class SubscriptionRenewalAttempt extends Model
{
    /** @use HasFactory<SubscriptionRenewalAttemptFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }
}
