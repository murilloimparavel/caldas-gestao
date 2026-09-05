<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerRetentionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerRetentionEvent>
 */
class CustomerRetentionEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['id' => (string) Str::uuid7(), 'customer_id' => Customer::factory(), 'tenant_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('tenant_id'), 'unit_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('unit_id'), 'event_type' => 'marked_at_risk', 'metadata' => [], 'occurred_at' => now()];
    }
}
