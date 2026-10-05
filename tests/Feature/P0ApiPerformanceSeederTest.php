<?php

use App\Models\Integrations\IntegrationCredential;
use Database\Seeders\P0ApiPerformanceSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    Passport::loadKeysFrom(p0ApiSeederPassportKeyDirectory());
    app(ClientRepository::class)->createPersonalAccessGrantClient('P0 API performance seeder tests', 'users');
    config(['performance.credentials_file' => p0ApiSeederCredentialsPath()]);
    config(['performance.allow_synthetic_seed' => true]);
});

afterEach(function (): void {
    $path = p0ApiSeederCredentialsPath();

    if (is_link($path) || file_exists($path)) {
        unlink($path);
    }
});

afterAll(function (): void {
    $directory = p0ApiSeederPassportKeyDirectory();

    foreach (['oauth-private.key', 'oauth-public.key'] as $file) {
        if (file_exists($directory.'/'.$file)) {
            unlink($directory.'/'.$file);
        }
    }

    if (is_dir($directory)) {
        rmdir($directory);
    }
});

function p0ApiSeederPassportKeyDirectory(): string
{
    static $directory;

    if (! is_string($directory)) {
        $directory = sys_get_temp_dir().'/p0-api-performance-passport-'.Str::uuid();
        mkdir($directory, 0700, true);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        expect($key)->not->toBeFalse();
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        file_put_contents($directory.'/oauth-private.key', $privateKey);
        file_put_contents($directory.'/oauth-public.key', $details['key']);
        chmod($directory.'/oauth-private.key', 0600);
        chmod($directory.'/oauth-public.key', 0600);
    }

    return $directory;
}

function p0ApiSeederCredentialsPath(): string
{
    static $path;

    return $path ??= sys_get_temp_dir().'/p0-api-performance-credentials-'.Str::uuid().'.json';
}

it('seeds read-only and proposal bearer credentials separately', function (): void {
    app(P0ApiPerformanceSeeder::class)->run();

    $credentials = json_decode(file_get_contents(p0ApiSeederCredentialsPath()), true, 512, JSON_THROW_ON_ERROR);

    expect($credentials)->toHaveKeys(['_fixture', 'p0-tenant-a', 'p0-tenant-b'])
        ->and($credentials['_fixture'])->toBe('caldas-p0-synthetic-v1')
        ->and(fileperms(p0ApiSeederCredentialsPath()) & 0777)->toBe(0600);

    $tokens = [];

    foreach (array_filter($credentials, fn (string $key): bool => $key !== '_fixture', ARRAY_FILTER_USE_KEY) as $tenantKey => $credential) {
        expect($credential)->toHaveKeys([
            'access_token',
            'proposal_access_token',
            'tenant_slug',
            'unit_slug',
        ])
            ->and($tenantKey)->toStartWith('p0-')
            ->and($credential['tenant_slug'])->toBe($tenantKey)
            ->and($credential['unit_slug'])->toStartWith('p0-')
            ->and($credential['access_token'])->not->toBeEmpty()
            ->and($credential['proposal_access_token'])->not->toBeEmpty()
            ->and($credential['access_token'])->not->toBe($credential['proposal_access_token']);

        $tokens[] = $credential['access_token'];
        $tokens[] = $credential['proposal_access_token'];

        $records = IntegrationCredential::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', $credential['tenant_slug']))
            ->whereHas('unit', fn ($query) => $query->where('slug', $credential['unit_slug']))
            ->get();

        expect($records)->toHaveCount(2)
            ->and($records->pluck('capabilities')->all())->toContain(['context:read', 'catalog:read', 'setup:read'])
            ->and($records->pluck('capabilities')->all())->toContain(['operations:propose']);
    }

    expect($tokens)->toHaveCount(4)
        ->and(array_unique($tokens))->toHaveCount(4);
});

it('requires the explicit synthetic seed opt-in', function (): void {
    config(['performance.allow_synthetic_seed' => false]);

    expect(fn (): mixed => app(P0ApiPerformanceSeeder::class)->run())
        ->toThrow(LogicException::class, 'P0_ALLOW_SYNTHETIC_SEED=true');
});

it('rejects a PostgreSQL connection outside the authorized loopback target', function (): void {
    $originalDatabaseDefault = config('database.default');
    $originalApplicationSchema = config('database.application_schema');
    $originalRuntimeRole = config('database.runtime_role');
    $originalPostgresConnection = config('database.connections.pgsql');

    config([
        'database.default' => 'pgsql',
        'database.application_schema' => 'app',
        'database.runtime_role' => 'caldas_runtime',
        'database.connections.pgsql' => [
            'driver' => 'pgsql',
            'host' => 'database.internal',
            'database' => 'caldas_gestao_e2e',
            'username' => 'caldas_runtime',
        ],
    ]);

    $connection = Mockery::mock();
    $connection->shouldReceive('getDriverName')->twice()->andReturn('pgsql');
    $connection->shouldReceive('getName')->once()->andReturn('pgsql');
    $database = Mockery::mock();
    $database->shouldReceive('connection')->once()->andReturn($connection);

    try {
        p0ApiSeederWithEnvironment('CI', 'true', function () use ($database): void {
            $original = DB::getFacadeRoot();
            DB::swap($database);

            try {
                expect(fn (): mixed => app(P0ApiPerformanceSeeder::class)->run())
                    ->toThrow(LogicException::class, 'loopback database host');
            } finally {
                DB::swap($original);
            }
        });
    } finally {
        config([
            'database.default' => $originalDatabaseDefault,
            'database.application_schema' => $originalApplicationSchema,
            'database.runtime_role' => $originalRuntimeRole,
            'database.connections.pgsql' => $originalPostgresConnection,
        ]);
    }
});

it('rejects a PostgreSQL DB_URL that could override the validated host and database', function (): void {
    $originalDatabaseDefault = config('database.default');
    $originalApplicationSchema = config('database.application_schema');
    $originalRuntimeRole = config('database.runtime_role');
    $originalPostgresConnection = config('database.connections.pgsql');

    config([
        'database.default' => 'pgsql',
        'database.application_schema' => 'app',
        'database.runtime_role' => 'caldas_runtime',
        'database.connections.pgsql' => [
            'driver' => 'pgsql',
            'url' => 'postgresql://attacker.example/caldas_gestao_e2e',
            'host' => '127.0.0.1',
            'database' => 'caldas_gestao_e2e',
            'username' => 'caldas_runtime',
        ],
    ]);

    $connection = Mockery::mock();
    $connection->shouldReceive('getDriverName')->twice()->andReturn('pgsql');
    $connection->shouldReceive('getName')->once()->andReturn('pgsql');
    $database = Mockery::mock();
    $database->shouldReceive('connection')->once()->andReturn($connection);

    try {
        p0ApiSeederWithEnvironment('CI', 'true', function () use ($database): void {
            $original = DB::getFacadeRoot();
            DB::swap($database);

            try {
                expect(fn (): mixed => app(P0ApiPerformanceSeeder::class)->run())
                    ->toThrow(LogicException::class, 'DB_URL');
            } finally {
                DB::swap($original);
            }
        });
    } finally {
        config([
            'database.default' => $originalDatabaseDefault,
            'database.application_schema' => $originalApplicationSchema,
            'database.runtime_role' => $originalRuntimeRole,
            'database.connections.pgsql' => $originalPostgresConnection,
        ]);
    }
});

it('rejects application schema CREATE access and runtime ownership', function (): void {
    $originalDatabaseDefault = config('database.default');
    $originalApplicationSchema = config('database.application_schema');
    $originalRuntimeRole = config('database.runtime_role');
    $originalPostgresConnection = config('database.connections.pgsql');

    config([
        'database.default' => 'pgsql',
        'database.application_schema' => 'app',
        'database.runtime_role' => 'caldas_runtime',
        'database.connections.pgsql' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => 'caldas_gestao_e2e',
            'username' => 'caldas_runtime',
        ],
    ]);

    $privileges = [
        (object) [
            'role' => 'caldas_runtime',
            'login_role' => 'caldas_runtime',
            'current_schema' => 'app',
            'application_schema_exists' => true,
            'application_schema_owner' => 'caldas_runtime',
            'has_application_schema_usage' => true,
            'has_application_schema_create' => false,
            'can_login' => true,
            'is_superuser' => false,
            'can_create_database' => false,
            'can_create_role' => false,
            'can_replicate' => false,
            'bypasses_rls' => false,
            'has_role_memberships' => false,
            'has_public_schema_create' => false,
        ],
        (object) [
            'role' => 'caldas_runtime',
            'login_role' => 'caldas_runtime',
            'current_schema' => 'app',
            'application_schema_exists' => true,
            'application_schema_owner' => 'postgres',
            'has_application_schema_usage' => true,
            'has_application_schema_create' => true,
            'can_login' => true,
            'is_superuser' => false,
            'can_create_database' => false,
            'can_create_role' => false,
            'can_replicate' => false,
            'bypasses_rls' => false,
            'has_role_memberships' => false,
            'has_public_schema_create' => false,
        ],
    ];
    $connection = Mockery::mock();
    $connection->shouldReceive('getDriverName')->times(4)->andReturn('pgsql');
    $connection->shouldReceive('getName')->twice()->andReturn('pgsql');
    $connection->shouldReceive('selectOne')->twice()->andReturn(...$privileges);
    $database = Mockery::mock();
    $database->shouldReceive('connection')->twice()->andReturn($connection);

    try {
        p0ApiSeederWithEnvironment('CI', 'true', function () use ($database): void {
            $original = DB::getFacadeRoot();
            DB::swap($database);

            try {
                $reflection = new ReflectionMethod(P0ApiPerformanceSeeder::class, 'assertSafeExecutionEnvironment');

                foreach ([1, 2] as $attempt) {
                    expect(fn (): mixed => $reflection->invoke(app(P0ApiPerformanceSeeder::class)))
                        ->toThrow(LogicException::class, 'least-privilege runtime role');
                }
            } finally {
                DB::swap($original);
            }
        });
    } finally {
        config([
            'database.default' => $originalDatabaseDefault,
            'database.application_schema' => $originalApplicationSchema,
            'database.runtime_role' => $originalRuntimeRole,
            'database.connections.pgsql' => $originalPostgresConnection,
        ]);
    }
});

it('accepts the two authorized PostgreSQL target types with a least privilege runtime role', function (): void {
    $originalDatabaseDefault = config('database.default');
    $originalApplicationSchema = config('database.application_schema');
    $originalRuntimeRole = config('database.runtime_role');
    $originalPostgresConnection = config('database.connections.pgsql');

    config([
        'database.default' => 'pgsql',
        'database.application_schema' => 'app',
        'database.runtime_role' => 'caldas_runtime',
        'database.connections.pgsql' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => 'caldas_gestao_e2e',
            'username' => 'caldas_runtime',
        ],
    ]);

    $privileges = (object) [
        'role' => 'caldas_runtime',
        'login_role' => 'caldas_runtime',
        'current_schema' => 'app',
        'application_schema_exists' => true,
        'application_schema_owner' => 'postgres',
        'has_application_schema_usage' => true,
        'has_application_schema_create' => false,
        'can_login' => true,
        'is_superuser' => false,
        'can_create_database' => false,
        'can_create_role' => false,
        'can_replicate' => false,
        'bypasses_rls' => false,
        'has_role_memberships' => false,
        'has_public_schema_create' => false,
    ];
    $connection = Mockery::mock();
    $connection->shouldReceive('getDriverName')->times(4)->andReturn('pgsql');
    $connection->shouldReceive('getName')->twice()->andReturn('pgsql');
    $connection->shouldReceive('selectOne')->twice()->andReturn($privileges);
    $database = Mockery::mock();
    $database->shouldReceive('connection')->twice()->andReturn($connection);

    try {
        p0ApiSeederWithEnvironment('CI', 'true', function () use ($database): void {
            $original = DB::getFacadeRoot();
            DB::swap($database);

            try {
                $reflection = new ReflectionMethod(P0ApiPerformanceSeeder::class, 'assertSafeExecutionEnvironment');

                foreach (['caldas_gestao_e2e', 'caldas_gestao_test'] as $databaseName) {
                    config(['database.connections.pgsql.database' => $databaseName]);
                    $reflection->invoke(app(P0ApiPerformanceSeeder::class));
                }

                expect(true)->toBeTrue();
            } finally {
                DB::swap($original);
            }
        });
    } finally {
        config([
            'database.default' => $originalDatabaseDefault,
            'database.application_schema' => $originalApplicationSchema,
            'database.runtime_role' => $originalRuntimeRole,
            'database.connections.pgsql' => $originalPostgresConnection,
        ]);
    }
});

it('does not follow a credentials file symlink', function (): void {
    $path = p0ApiSeederCredentialsPath();
    $victim = $path.'.victim';
    file_put_contents($victim, 'sentinel');
    symlink($victim, $path);

    expect(fn (): mixed => p0ApiSeederWriteCredentials(['_fixture' => 'test']))
        ->toThrow(LogicException::class, 'symbolic link');
    expect(file_get_contents($victim))->toBe('sentinel');

    unlink($path);
    unlink($victim);
});

it('rejects a credentials path outside the canonical temporary directory', function (): void {
    $path = dirname(sys_get_temp_dir()).'/p0-api-performance-outside-'.Str::uuid().'.json';
    config(['performance.credentials_file' => $path]);

    expect(fn (): mixed => p0ApiSeederWriteCredentials(['_fixture' => 'test']))
        ->toThrow(LogicException::class, 'direct child of the canonical temporary directory');
});

it('rejects a credentials path below a parent symlink', function (): void {
    $temporaryDirectory = realpath(sys_get_temp_dir());
    $parentLink = $temporaryDirectory.'/p0-api-performance-parent-'.Str::uuid();
    $path = $parentLink.'/credentials.json';
    symlink($temporaryDirectory, $parentLink);
    config(['performance.credentials_file' => $path]);

    try {
        expect(fn (): mixed => p0ApiSeederWriteCredentials(['_fixture' => 'test']))
            ->toThrow(LogicException::class, 'direct child of the canonical temporary directory');
    } finally {
        unlink($parentLink);
    }
});

it('does not replace an existing credentials file', function (): void {
    $path = p0ApiSeederCredentialsPath();
    file_put_contents($path, 'sentinel');

    try {
        expect(fn (): mixed => p0ApiSeederWriteCredentials(['_fixture' => 'test']))
            ->toThrow(LogicException::class, 'must not overwrite an existing file');
        expect(file_get_contents($path))->toBe('sentinel');
    } finally {
        unlink($path);
    }
});

function p0ApiSeederWriteCredentials(array $credentials): void
{
    $reflection = new ReflectionMethod(P0ApiPerformanceSeeder::class, 'writeCredentialsFile');
    $reflection->invoke(app(P0ApiPerformanceSeeder::class), $credentials);
}

/**
 * @param  Closure(): void  $callback
 */
function p0ApiSeederWithEnvironment(string $key, string $value, Closure $callback): void
{
    $environment = [
        'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
        'env_exists' => array_key_exists($key, $_ENV),
        'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
        'server_exists' => array_key_exists($key, $_SERVER),
        'getenv' => getenv($key),
    ];
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key.'='.$value);

    try {
        $callback();
    } finally {
        if ($environment['env_exists']) {
            $_ENV[$key] = $environment['env'];
        } else {
            unset($_ENV[$key]);
        }

        if ($environment['server_exists']) {
            $_SERVER[$key] = $environment['server'];
        } else {
            unset($_SERVER[$key]);
        }

        $environment['getenv'] === false ? putenv($key) : putenv($key.'='.$environment['getenv']);
    }
}
