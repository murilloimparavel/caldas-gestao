<?php

namespace Database\Factories;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OutboxEvent> */
class OutboxEventFactory extends Factory
{
    public function definition(): array
    {
        $occurredAt = now();

        return [
            'event_id' => (string) Str::uuid7(),
            'tenant_id' => Tenant::factory(),
            'unit_id' => null,
            'actor_user_id' => null,
            'aggregate_type' => 'test',
            'aggregate_id' => (string) Str::uuid7(),
            'aggregate_version' => 1,
            'event_type' => 'test.performed',
            'event_version' => 1,
            'correlation_id' => (string) Str::uuid7(),
            'causation_id' => null,
            'payload' => [],
            'status' => OutboxStatus::Pending,
            'occurred_at' => $occurredAt,
            'available_at' => $occurredAt,
            'last_attempt_at' => null,
            'published_at' => null,
            'dead_at' => null,
            'locked_at' => null,
            'lease_until' => null,
            'locked_by' => null,
            'attempts' => 0,
            'last_error' => null,
            'created_at' => $occurredAt,
        ];
    }
}
