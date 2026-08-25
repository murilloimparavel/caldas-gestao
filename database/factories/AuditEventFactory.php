<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditEvent> */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => (string) Str::uuid7(),
            'tenant_id' => Tenant::factory(),
            'unit_id' => null,
            'actor_user_id' => User::factory(),
            'action' => 'test.performed',
            'resource_type' => 'test',
            'resource_id' => (string) Str::uuid7(),
            'request_id' => (string) Str::uuid7(),
            'correlation_id' => (string) Str::uuid7(),
            'reason' => null,
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
