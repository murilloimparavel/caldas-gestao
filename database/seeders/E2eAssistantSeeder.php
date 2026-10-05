<?php

namespace Database\Seeders;

use App\Actions\Identity\OnboardTenant;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class E2eAssistantSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('E2eAssistantSeeder is restricted to APP_ENV=testing.');
        }

        $ownerEmail = $this->required('E2E_OWNER_EMAIL');
        $ownerPassword = $this->required('E2E_OWNER_PASSWORD');
        $collaboratorEmail = $this->required('E2E_COLLABORATOR_EMAIL');
        $collaboratorPassword = $this->required('E2E_COLLABORATOR_PASSWORD');
        $tenantSlug = $this->required('E2E_TENANT_SLUG');
        $tenantName = $this->required('E2E_TENANT_NAME');
        $unitSlug = $this->required('E2E_UNIT_SLUG');
        $unitName = $this->required('E2E_UNIT_NAME');

        DB::transaction(function () use (
            $ownerEmail,
            $ownerPassword,
            $collaboratorEmail,
            $collaboratorPassword,
            $tenantSlug,
            $tenantName,
            $unitSlug,
            $unitName,
        ): void {
            $this->call(PermissionCatalogSeeder::class);

            $owner = $this->upsertUser($ownerEmail, $ownerPassword, 'E2E Assistente Owner');
            $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

            if ($tenant === null) {
                $tenant = (new OnboardTenant)->handle($owner, [
                    'name' => $tenantName,
                    'slug' => $tenantSlug,
                    'timezone' => 'America/Sao_Paulo',
                    'default_currency' => 'BRL',
                ], [
                    'name' => $unitName,
                    'slug' => $unitSlug,
                    'timezone' => 'America/Sao_Paulo',
                ]);
            }

            $unit = $tenant->units()->where('slug', $unitSlug)->first();
            if ($unit === null) {
                throw new \LogicException('The E2E tenant fixture is missing its configured unit.');
            }

            $this->normalizeUser($owner, $ownerPassword);
            $this->ensureOwnerMembership($owner, $tenant, $unit);
            $this->ensureActiveSubscription($tenant);

            $collaborator = $this->upsertUser($collaboratorEmail, $collaboratorPassword, 'E2E Assistente Colaborador');
            $this->ensureCollaboratorMembership($collaborator, $tenant, $unit);
        });
    }

    private function required(string $key): string
    {
        $configKey = Str::of($key)->lower()->after('e2e_')->toString();
        $value = trim((string) config("e2e.{$configKey}", ''));

        if ($value === '') {
            throw new \LogicException("Missing required E2E fixture variable: {$key}.");
        }

        return $value;
    }

    private function upsertUser(string $email, string $password, string $name): User
    {
        $user = User::query()->where('email_normalized', mb_strtolower($email))->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'must_change_password' => false,
                'first_login_at' => now(),
            ]);
        }

        $this->normalizeUser($user, $password);

        return $user->fresh();
    }

    private function normalizeUser(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'first_login_at' => now(),
        ])->save();
    }

    private function ensureOwnerMembership(User $owner, Tenant $tenant, Unit $unit): void
    {
        $membership = Membership::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'user_id' => $owner->getKey(),
            ],
            [
                'status' => MembershipStatus::Active->value,
                'joined_at' => now(),
            ],
        );

        $membership->forceFill([
            'status' => MembershipStatus::Active->value,
            'joined_at' => $membership->joined_at ?? now(),
            'revoked_at' => null,
        ])->save();

        MembershipUnit::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'unit_id' => $unit->getKey(),
            ],
            ['is_primary' => true],
        );

        $ownerRole = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('key', 'owner')
            ->where('is_system', true)
            ->first();

        if ($ownerRole === null) {
            throw new \LogicException('The E2E tenant fixture is missing its owner role.');
        }

        if (! $membership->membershipRoles()->where('role_id', $ownerRole->getKey())->whereNull('revoked_at')->exists()) {
            throw new \LogicException('The E2E tenant fixture owner is not assigned to its owner role.');
        }
    }

    private function ensureCollaboratorMembership(User $collaborator, Tenant $tenant, Unit $unit): void
    {
        $membership = Membership::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'user_id' => $collaborator->getKey(),
            ],
            [
                'status' => MembershipStatus::Active->value,
                'joined_at' => now(),
            ],
        );

        $membership->forceFill([
            'status' => MembershipStatus::Active->value,
            'joined_at' => $membership->joined_at ?? now(),
            'revoked_at' => null,
        ])->save();

        MembershipUnit::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'unit_id' => $unit->getKey(),
            ],
            ['is_primary' => true],
        );
    }

    private function ensureActiveSubscription(Tenant $tenant): void
    {
        $subscription = TenantSubscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->latest()
            ->first();

        if ($subscription === null) {
            TenantSubscription::factory()->create([
                'tenant_id' => $tenant->getKey(),
                'status' => 'active',
                'starts_at' => now()->subMinute(),
                'ends_at' => now()->addYear(),
            ]);

            return;
        }

        $subscription->forceFill([
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?? now()->subMinute(),
            'ends_at' => now()->addYear(),
            'grace_ends_at' => null,
        ])->save();
    }
}
