<?php

namespace App\Models;

use Database\Factories\BillingWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'provider_event_id', 'event', 'payload', 'status', 'attempts', 'last_error', 'received_at', 'processed_at'])]
class BillingWebhookEvent extends Model
{
    /** @use HasFactory<BillingWebhookEventFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
