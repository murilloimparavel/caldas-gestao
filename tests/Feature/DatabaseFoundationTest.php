<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('generates UUIDv7 identifiers for users', function () {
    $user = User::factory()->create();

    expect($user->getKey())
        ->toBeString()
        ->and(Str::isUuid($user->getKey(), 7))->toBeTrue()
        ->and($user->getIncrementing())->toBeFalse()
        ->and($user->getKeyType())->toBe('string');
});

it('persists UUID user references for sessions and passkeys', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $user->getKey(),
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $passkey = $user->passkeys()->create([
        'name' => 'Test authenticator',
        'credential_id' => Str::random(64),
        'credential' => [],
    ]);

    expect($passkey->user->is($user))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('uses native PostgreSQL UUID columns in the integration gate', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Native UUID column types are enforced by the PostgreSQL CI job.');
    }

    expect(Schema::getColumnType('users', 'id'))->toBe('uuid')
        ->and(Schema::getColumnType('sessions', 'user_id'))->toBe('uuid')
        ->and(Schema::getColumnType('passkeys', 'user_id'))->toBe('uuid')
        ->and(DB::selectOne('select current_schema() as schema')->schema)->toBe('app');
});

it('keeps runtime and migration connection paths explicit in PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Separate PostgreSQL connection paths are enforced by the PostgreSQL CI job.');
    }

    expect(config('database.default'))->toBe('pgsql')
        ->and(DB::connection()->getName())->toBe('pgsql')
        ->and(DB::selectOne('select current_user as role')->role)->toBe(config('database.runtime_role'))
        ->and(config('database.connections.migration.driver'))->toBe('pgsql')
        ->and(DB::connection('migration')->getName())->toBe('migration')
        ->and(DB::connection('migration')->getDriverName())->toBe('pgsql');
});

it('provisions schemas idempotently and skips non PostgreSQL databases', function () {
    $database = DB::getDriverName() === 'pgsql' ? 'migration' : config('database.default');

    $this->artisan('db:provision-schema', ['--database' => $database])
        ->assertSuccessful();

    $this->artisan('db:provision-schema', ['--database' => $database])
        ->assertSuccessful();
});

it('rejects unsafe PostgreSQL schema identifiers', function () {
    config(['database.application_schema' => 'app;drop schema public']);

    $this->artisan('db:provision-schema', ['--database' => config('database.default')])
        ->assertFailed();
});

it('rejects unsafe runtime role identifiers before provisioning', function () {
    config(['database.runtime_role' => 'runtime;drop role postgres']);

    $this->artisan('db:provision-schema', ['--database' => config('database.default')])
        ->assertFailed();
});

it('requires a runtime role in strict PostgreSQL provisioning', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Strict PostgreSQL provisioning is enforced by the PostgreSQL CI job.');
    }

    config(['database.runtime_role' => null]);

    $this->artisan('db:provision-schema', [
        '--database' => 'migration',
        '--strict' => true,
    ])->assertFailed();
});

it('rejects a runtime connection whose effective role does not match configuration', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Runtime identity is enforced by the PostgreSQL CI job.');
    }

    config(['database.runtime_role' => 'different_runtime_role']);

    $this->artisan('db:provision-schema', ['--database' => 'migration'])
        ->assertFailed();
});

it('grants the configured runtime role access without schema ownership', function () {
    if (DB::getDriverName() !== 'pgsql' || ! is_string(config('database.runtime_role'))) {
        $this->markTestSkipped('Runtime grants are enforced by the PostgreSQL CI job.');
    }

    $role = config('database.runtime_role');

    $this->artisan('db:provision-schema', ['--database' => 'migration'])
        ->assertSuccessful();

    $privileges = DB::connection('migration')->selectOne(
        <<<'SQL'
        select
            has_schema_privilege(?, ?, 'USAGE') as has_schema_usage,
            has_schema_privilege(?, ?, 'CREATE') as has_schema_create,
            has_table_privilege(?, 'app.users', 'SELECT,INSERT,UPDATE,DELETE') as has_table_access
        SQL,
        [$role, 'app', $role, 'app', $role],
    );

    expect($privileges->has_schema_usage)->toBeTrue()
        ->and($privileges->has_schema_create)->toBeFalse()
        ->and($privileges->has_table_access)->toBeTrue();
});

it('allows runtime DML while denying runtime DDL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Least-privilege runtime behavior is enforced by the PostgreSQL CI job.');
    }

    $user = User::factory()->create();

    $this->assertModelExists($user);
    DB::statement('SAVEPOINT runtime_ddl_check');

    try {
        DB::statement('CREATE TABLE app.runtime_must_not_create (id bigint primary key)');
        $this->fail('The runtime connection unexpectedly created a table.');
    } catch (QueryException $exception) {
        DB::statement('ROLLBACK TO SAVEPOINT runtime_ddl_check');

        expect($exception->getCode())->toBe('42501');
    }
});

it('uses varchar 320 for both email representations in PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL column lengths are enforced by the PostgreSQL CI job.');
    }

    $columns = DB::select(
        <<<'SQL'
        select column_name, character_maximum_length
        from information_schema.columns
        where table_schema = 'app'
          and table_name = 'users'
          and column_name in ('email', 'email_normalized')
        order by column_name
        SQL,
    );

    expect(collect($columns)->pluck('character_maximum_length', 'column_name')->all())->toBe([
        'email' => 320,
        'email_normalized' => 320,
    ]);
});
