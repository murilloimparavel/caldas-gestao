<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerCommunicationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerCommunicationPreference>
 */
class CustomerCommunicationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['id' => (string) Str::uuid7(), 'customer_id' => Customer::factory(), 'tenant_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('tenant_id'), 'unit_id' => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('unit_id'), 'channel' => 'email', 'opted_in' => true, 'source' => 'staff', 'consented_at' => now(), 'revoked_at' => fn (array $attributes) => ($attributes['opted_in'] ?? true) ? null : now()->subSecond(), 'lock_version' => 0];
    }
}
