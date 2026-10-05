<?php

namespace Database\Factories\Integrations;

use App\Models\Integrations\OAuthGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthGrant>
 */
class OAuthGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'client_id' => fn (): string => (string) Str::uuid(),
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'unit_id' => null,
            'resource' => 'https://example.test/mcp',
            'capabilities' => ['mcp:use'],
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
