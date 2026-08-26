<?php

namespace Database\Factories;

use App\Models\FinancialObligation;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinancialObligation>
 */
class FinancialObligationFactory extends Factory
{
    protected $model = FinancialObligation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes): string => (string) Unit::query()->whereKey($attributes['unit_id'])->value('tenant_id'),
            'type' => fake()->randomElement(['payable', 'receivable']),
            'category_id' => null,
            'supplier_id' => null,
            'customer_id' => null,
            'description' => fake()->sentence(3),
            'amount_cents' => fake()->numberBetween(1000, 50000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'paid_date' => null,
            'status' => 'pending',
            'payment_method' => null,
            'notes' => fake()->optional()->sentence(),
            'lock_version' => 1,
        ];
    }

    public function payable(): static
    {
        return $this->state(fn () => [
            'type' => 'payable',
        ]);
    }

    public function receivable(): static
    {
        return $this->state(fn () => [
            'type' => 'receivable',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
            'payment_method' => 'pix',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
        ]);
    }
}
