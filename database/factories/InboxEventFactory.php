<?php

namespace Database\Factories;

use App\Enums\InboxStatus;
use App\Models\InboxEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InboxEvent> */
class InboxEventFactory extends Factory
{
    public function definition(): array
    {
        $receivedAt = now();

        return [
            'tenant_id' => Tenant::factory(),
            'consumer' => 'test.consumer',
            'event_id' => (string) Str::uuid7(),
            'event_type' => 'test.performed',
            'event_version' => 1,
            'correlation_id' => (string) Str::uuid7(),
            'causation_id' => null,
            'payload' => [],
            'status' => InboxStatus::Received,
            'attempts' => 0,
            'received_at' => $receivedAt,
            'processed_at' => null,
            'available_at' => $receivedAt,
            'last_attempt_at' => null,
            'dead_at' => null,
            'locked_at' => null,
            'lease_until' => null,
            'locked_by' => null,
            'last_error' => null,
        ];
    }
}
