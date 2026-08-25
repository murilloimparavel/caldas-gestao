<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipUnit>
 */
class MembershipUnitFactory extends Factory
{
    public function forMembership(Membership $membership): static
    {
        return $this->state([
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->getKey(),
        ]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'tenant_id' => $unit->tenant_id,
            'unit_id' => $unit->getKey(),
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'membership_id' => null,
            'unit_id' => null,
            'is_primary' => false,
        ];
    }
}
