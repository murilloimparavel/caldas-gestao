<?php

namespace Database\Seeders;

use App\Actions\Identity\OnboardTenant;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OwnerPermissionCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed only the local/testing fixture workspace.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $this->call(PermissionCatalogSeeder::class);

            $admin = User::query()->where('email_normalized', 'teste@caldas.local')->first();

            if ($admin === null) {
                $admin = User::query()->create([
                    'name' => 'Usuário Teste',
                    'email' => 'teste@caldas.local',
                    'password' => 'Caldas@2026!',
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            }

            $tenant = Tenant::query()->where('slug', 'teste')->first();

            if ($tenant === null) {
                (new OnboardTenant)->handle($admin, [
                    'name' => 'Caldas Gestão Teste',
                    'slug' => 'teste',
                    'timezone' => 'America/Sao_Paulo',
                    'default_currency' => 'BRL',
                ], [
                    'name' => 'Matriz',
                    'slug' => 'matriz',
                    'timezone' => 'America/Sao_Paulo',
                ]);

                return;
            }

            $membership = Membership::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $admin->getKey())
                ->where('status', 'active')
                ->exists();

            $ownerRole = Role::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('key', 'owner')
                ->where('is_system', true)
                ->first();

            if (! $membership || $ownerRole === null) {
                throw new \LogicException('The local seed tenant exists but is not owned by the local fixture user.');
            }

            app(OwnerPermissionCatalog::class)->ensure($tenant, $ownerRole);
        }, 5);
    }
}
