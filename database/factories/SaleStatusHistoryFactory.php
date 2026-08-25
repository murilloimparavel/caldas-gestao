<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaleStatusHistory>
 */
class SaleStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'sale_id' => Sale::factory(),
            'from_status' => 'draft',
            'to_status' => 'open',
            'user_id' => User::factory(),
            'reason' => fake()->sentence(),
        ];
    }
}
