<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipRole;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipRole>
 */
class MembershipRoleFactory extends Factory
{
    public function forMembership(Membership $membership): static
    {
        return $this->state([
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->getKey(),
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state([
            'tenant_id' => $role->tenant_id,
            'role_id' => $role->getKey(),
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
            'role_id' => null,
            'scope_kind' => 'tenant',
            'assignment_scope' => 'tenant',
            'unit_id' => null,
            'revoked_at' => null,
        ];
    }
}
