<?php

namespace Database\Seeders;

use App\Actions\Identity\OnboardTenant;
use App\Models\AvailabilityRule;
use App\Models\Category;
use App\Models\Integrations\IntegrationCredential;
use App\Models\Membership;
use App\Models\MembershipUnit;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

final class P0ApiPerformanceSeeder extends Seeder
{
    private const FIXTURE = 'caldas-p0-synthetic-v1';

    /**
     * Create synthetic, tenant/unit-bound credentials for the P0 job.
     *
     * The read credential is limited to context, catalog, and setup reads. The
     * separate proposal credential can only create pending proposals and never
     * confirms or executes an operation.
     */
    public function run(): void
    {
        $this->assertSafeExecutionEnvironment();

        $tenantCredentials = DB::transaction(function (): array {
            $this->call(PermissionCatalogSeeder::class);
            app(ClientRepository::class)->createPersonalAccessGrantClient('P0 API performance', 'users');

            $ownerA = $this->owner('p0-owner-a@caldas.test', 'P0 Owner A');
            $ownerB = $this->owner('p0-owner-b@caldas.test', 'P0 Owner B');

            $tenantA = (new OnboardTenant)->handle($ownerA, [
                'name' => 'P0 Tenant A',
                'slug' => 'p0-tenant-a',
                'timezone' => 'America/Sao_Paulo',
                'default_currency' => 'BRL',
            ], [
                'name' => 'P0 Unidade A1',
                'slug' => 'p0-a1',
                'timezone' => 'America/Sao_Paulo',
            ]);
            $tenantB = (new OnboardTenant)->handle($ownerB, [
                'name' => 'P0 Tenant B',
                'slug' => 'p0-tenant-b',
                'timezone' => 'America/Sao_Paulo',
                'default_currency' => 'BRL',
            ], [
                'name' => 'P0 Unidade B1',
                'slug' => 'p0-b1',
                'timezone' => 'America/Sao_Paulo',
            ]);

            TenantSubscription::factory()->create(['tenant_id' => $tenantA->getKey(), 'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addYear()]);
            TenantSubscription::factory()->create(['tenant_id' => $tenantB->getKey(), 'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addYear()]);

            $unitA1 = $tenantA->units()->where('slug', 'p0-a1')->firstOrFail();
            $unitA2 = Unit::query()->create([
                'tenant_id' => $tenantA->getKey(),
                'name' => 'P0 Unidade A2',
                'slug' => 'p0-a2',
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ]);
            $unitB1 = $tenantB->units()->where('slug', 'p0-b1')->firstOrFail();

            $membershipA = Membership::query()->where('tenant_id', $tenantA->getKey())->where('user_id', $ownerA->getKey())->firstOrFail();
            MembershipUnit::query()->create([
                'tenant_id' => $tenantA->getKey(),
                'membership_id' => $membershipA->getKey(),
                'unit_id' => $unitA2->getKey(),
                'is_primary' => false,
            ]);

            $this->seedCatalog($tenantA, $unitA1, 20, 100, 20);
            $this->seedCatalog($tenantA, $unitA2, 5, 20, 5);
            $this->seedCatalog($tenantB, $unitB1, 8, 40, 8);

            return [
                $tenantA->slug => $this->credential($ownerA, $tenantA, $unitA1),
                $tenantB->slug => $this->credential($ownerB, $tenantB, $unitB1),
            ];
        });

        $this->writeCredentialsFile([
            '_fixture' => self::FIXTURE,
            ...$tenantCredentials,
        ]);
    }

    private function assertSafeExecutionEnvironment(): void
    {
        if (! app()->environment('testing')) {
            throw new \LogicException('P0ApiPerformanceSeeder is restricted to APP_ENV=testing.');
        }

        if (! $this->environmentFlag('P0_ALLOW_SYNTHETIC_SEED', 'performance.allow_synthetic_seed')) {
            throw new \LogicException('P0ApiPerformanceSeeder requires P0_ALLOW_SYNTHETIC_SEED=true.');
        }

        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
            if (! app()->runningUnitTests()) {
                throw new \LogicException('SQLite :memory: is only allowed while running unit tests.');
            }

            return;
        }

        if ($connection->getDriverName() !== 'pgsql') {
            throw new \LogicException('P0 synthetic seeding requires SQLite :memory: tests or an authorized PostgreSQL CI connection.');
        }

        if (! $this->environmentFlag('CI')) {
            throw new \LogicException('PostgreSQL synthetic seeding is restricted to CI.');
        }

        $connectionName = $connection->getName();
        $connectionConfig = config("database.connections.{$connectionName}");
        $host = is_array($connectionConfig) ? (string) ($connectionConfig['host'] ?? '') : '';
        $database = is_array($connectionConfig) ? (string) ($connectionConfig['database'] ?? '') : '';
        $username = is_array($connectionConfig) ? (string) ($connectionConfig['username'] ?? '') : '';
        $url = is_array($connectionConfig) ? $connectionConfig['url'] ?? null : null;
        $runtimeRole = config('database.runtime_role');

        if ($url !== null && (! is_string($url) || trim($url) !== '')) {
            throw new \LogicException('P0 synthetic PostgreSQL seeding does not allow DB_URL to override the validated target.');
        }

        if (! $this->isLoopbackHost($host)) {
            throw new \LogicException('P0 synthetic PostgreSQL seeding requires a loopback database host.');
        }

        if (! Str::endsWith($database, ['_e2e', '_test'])) {
            throw new \LogicException('P0 synthetic PostgreSQL seeding requires a database ending in _e2e or _test.');
        }

        if (config('database.application_schema') !== 'app') {
            throw new \LogicException('P0 synthetic PostgreSQL seeding requires DB_SCHEMA=app.');
        }

        if (! is_string($runtimeRole) || $runtimeRole === '' || $username !== $runtimeRole) {
            throw new \LogicException('P0 synthetic PostgreSQL seeding requires the configured runtime role as DB_USERNAME.');
        }

        $privileges = $connection->selectOne(
            <<<'SQL'
            SELECT
                current_user AS role,
                session_user AS login_role,
                current_schema() AS current_schema,
                EXISTS (
                    SELECT 1
                    FROM pg_catalog.pg_namespace
                    WHERE nspname = 'app'
                ) AS application_schema_exists,
                pg_catalog.pg_get_userbyid(application_schema.nspowner) AS application_schema_owner,
                pg_catalog.has_schema_privilege(current_user, 'app', 'USAGE') AS has_application_schema_usage,
                pg_catalog.has_schema_privilege(current_user, 'app', 'CREATE') AS has_application_schema_create,
                pg_catalog.pg_roles.rolcanlogin AS can_login,
                pg_catalog.pg_roles.rolsuper AS is_superuser,
                pg_catalog.pg_roles.rolcreatedb AS can_create_database,
                pg_catalog.pg_roles.rolcreaterole AS can_create_role,
                pg_catalog.pg_roles.rolreplication AS can_replicate,
                pg_catalog.pg_roles.rolbypassrls AS bypasses_rls,
                EXISTS (
                    SELECT 1
                    FROM pg_catalog.pg_auth_members
                    WHERE member = pg_catalog.pg_roles.oid
                ) AS has_role_memberships,
                pg_catalog.has_schema_privilege(current_user, 'public', 'CREATE') AS has_public_schema_create
            FROM pg_catalog.pg_roles
            LEFT JOIN pg_catalog.pg_namespace AS application_schema
                ON application_schema.nspname = 'app'
            WHERE pg_catalog.pg_roles.rolname = current_user
            SQL,
        );

        if ($privileges === null
            || $privileges->role !== $runtimeRole
            || $privileges->login_role !== $runtimeRole
            || $privileges->current_schema !== 'app'
            || ! (bool) $privileges->application_schema_exists
            || ! is_string($privileges->application_schema_owner)
            || $privileges->application_schema_owner === $runtimeRole
            || ! (bool) $privileges->has_application_schema_usage
            || (bool) $privileges->has_application_schema_create
            || ! (bool) $privileges->can_login
            || (bool) $privileges->is_superuser
            || (bool) $privileges->can_create_database
            || (bool) $privileges->can_create_role
            || (bool) $privileges->can_replicate
            || (bool) $privileges->bypasses_rls
            || (bool) $privileges->has_role_memberships
            || (bool) $privileges->has_public_schema_create) {
            throw new \LogicException('P0 synthetic PostgreSQL seeding requires a least-privilege runtime role.');
        }
    }

    private function environmentFlag(string $key, ?string $configKey = null): bool
    {
        if ($configKey !== null && config($configKey) !== null) {
            return filter_var(config($configKey), FILTER_VALIDATE_BOOL) === true;
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }

    private function isLoopbackHost(string $host): bool
    {
        $normalizedHost = trim($host, " \t\n\r\0\x0B[]");
        $packedAddress = inet_pton($normalizedHost);

        if ($packedAddress === false) {
            return false;
        }

        return (strlen($packedAddress) === 4 && ord($packedAddress[0]) === 127)
            || $packedAddress === str_repeat("\0", 15)."\1";
    }

    /** @param array<string, mixed> $credentials */
    private function writeCredentialsFile(array $credentials): void
    {
        $path = (string) config('performance.credentials_file');
        $temporaryDirectory = sys_get_temp_dir();
        $canonicalTemporaryDirectory = realpath($temporaryDirectory);

        if ($canonicalTemporaryDirectory === false || ! is_dir($temporaryDirectory)) {
            throw new \LogicException('P0 credentials file requires a canonical temporary directory.');
        }

        $directory = dirname($path);
        $allowedDirectories = array_unique([$temporaryDirectory, $canonicalTemporaryDirectory]);

        if ($path === '' || ! Str::startsWith($path, DIRECTORY_SEPARATOR) || ! in_array($directory, $allowedDirectories, true)) {
            throw new \LogicException('P0 credentials file must be a direct child of the canonical temporary directory.');
        }

        if (is_link($directory) || realpath($directory) !== $canonicalTemporaryDirectory) {
            throw new \LogicException('P0 credentials file parent must not be a symbolic link.');
        }

        if (is_link($path)) {
            throw new \LogicException('P0 credentials file must not be a symbolic link.');
        }

        if (file_exists($path)) {
            throw new \LogicException('P0 credentials file must not overwrite an existing file.');
        }

        $temporaryPath = tempnam($canonicalTemporaryDirectory, '.caldas-p0-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create the temporary P0 credentials file.');
        }

        try {
            if (! chmod($temporaryPath, 0600)) {
                throw new \RuntimeException('Unable to secure the temporary P0 credentials file.');
            }

            $encoded = json_encode($credentials, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
            $written = file_put_contents($temporaryPath, $encoded, LOCK_EX);

            if ($written !== strlen($encoded)) {
                throw new \RuntimeException('Unable to write the complete P0 credentials file.');
            }

            if (file_exists($path)) {
                throw new \LogicException('P0 credentials file must not overwrite an existing file.');
            }

            if (! link($temporaryPath, $path)) {
                throw new \LogicException('P0 credentials file must be created exclusively.');
            }
        } finally {
            if (is_file($temporaryPath) || is_link($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function owner(string $email, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'must_change_password' => false,
            'first_login_at' => now(),
        ]);
    }

    private function seedCatalog(Tenant $tenant, Unit $unit, int $categoryCount, int $serviceCount, int $professionalCount): void
    {
        $categories = collect(range(1, $categoryCount))->map(fn (int $index): Category => Category::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => sprintf('P0 %s Categoria %03d', $unit->slug, $index),
        ]));
        $services = collect(range(1, $serviceCount))->map(fn (int $index): Service => Service::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'category_id' => $categories->get(($index - 1) % $categories->count())->getKey(),
            'name' => sprintf('P0 %s Serviço %03d', $unit->slug, $index),
        ]));
        $professionals = collect(range(1, $professionalCount))->map(fn (int $index): Professional => Professional::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'name' => sprintf('P0 %s Profissional %03d', $unit->slug, $index),
        ]));

        foreach ($professionals as $index => $professional) {
            $professional->services()->attach($services->slice($index % max(1, $services->count()), 3)->pluck('id')->all(), [
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
            ]);
            AvailabilityRule::factory()->create([
                'tenant_id' => $tenant->getKey(),
                'unit_id' => $unit->getKey(),
                'professional_id' => $professional->getKey(),
            ]);
        }
    }

    /** @return array{access_token: string, proposal_access_token: string, tenant_slug: string, unit_slug: string} */
    private function credential(User $owner, Tenant $tenant, Unit $unit): array
    {
        $readCapabilities = ['context:read', 'catalog:read', 'setup:read'];
        $readIssued = $owner->createToken('P0 API performance read '.$tenant->slug, $readCapabilities);
        $readToken = $readIssued->getToken();

        IntegrationCredential::query()->create([
            'id' => (string) Str::uuid(),
            'passport_token_id' => $readToken->getKey(),
            'user_id' => $owner->getKey(),
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'label' => 'P0 API performance read',
            'capabilities' => $readCapabilities,
            'expires_at' => $readToken->expires_at,
        ]);

        $proposalCapabilities = ['operations:propose'];
        $proposalIssued = $owner->createToken('P0 API performance proposal '.$tenant->slug, $proposalCapabilities);
        $proposalToken = $proposalIssued->getToken();

        IntegrationCredential::query()->create([
            'id' => (string) Str::uuid(),
            'passport_token_id' => $proposalToken->getKey(),
            'user_id' => $owner->getKey(),
            'tenant_id' => $tenant->getKey(),
            'unit_id' => $unit->getKey(),
            'label' => 'P0 API performance proposal',
            'capabilities' => $proposalCapabilities,
            'expires_at' => $proposalToken->expires_at,
        ]);

        return [
            'access_token' => $readIssued->accessToken,
            'proposal_access_token' => $proposalIssued->accessToken,
            'tenant_slug' => $tenant->slug,
            'unit_slug' => $unit->slug,
        ];
    }
}
