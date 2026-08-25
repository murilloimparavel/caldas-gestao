<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('db:provision-schema
    {--database=migration : Database connection used to provision the schema}
    {--strict : Fail when no runtime role is configured}')]
#[Description('Create the private PostgreSQL application schema when it does not exist')]
class ProvisionDatabaseSchema extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schema = config('database.application_schema');

        if (! is_string($schema) || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $schema)) {
            $this->components->error('DB_SCHEMA must be a lowercase PostgreSQL identifier.');

            return self::FAILURE;
        }

        $runtimeRole = config('database.runtime_role');

        if ($runtimeRole !== null && $runtimeRole !== '' && (! is_string($runtimeRole) || ! preg_match('/\A[a-z][a-z0-9_]*\z/', $runtimeRole))) {
            $this->components->error('DB_RUNTIME_ROLE must be a lowercase PostgreSQL identifier.');

            return self::FAILURE;
        }

        if (($runtimeRole === null || $runtimeRole === '') && $this->requiresRuntimeRole()) {
            $this->components->error('DB_RUNTIME_ROLE is required for strict or production provisioning.');

            return self::FAILURE;
        }

        if (is_string($runtimeRole) && ! $this->runtimeIdentityMatches($runtimeRole)) {
            return self::FAILURE;
        }

        $connection = DB::connection($this->databaseConnectionName());

        if ($connection->getDriverName() !== 'pgsql') {
            $this->components->info('Schema provisioning skipped because the selected connection is not PostgreSQL.');

            return self::SUCCESS;
        }

        $connection->statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', $schema));

        if ($runtimeRole === null || $runtimeRole === '') {
            $this->components->warn('DB_RUNTIME_ROLE is not configured; runtime grants were skipped.');
            $this->components->info("PostgreSQL schema [{$schema}] is ready.");

            return self::SUCCESS;
        }

        $this->grantRuntimePrivileges($connection, $schema, $runtimeRole);
        $this->components->info("PostgreSQL schema [{$schema}] is ready.");

        return self::SUCCESS;
    }

    private function databaseConnectionName(): string
    {
        $database = $this->option('database');

        return Str::of(is_string($database) ? $database : 'migration')->trim()->toString();
    }

    private function requiresRuntimeRole(): bool
    {
        return $this->option('strict') || config('database.provisioning_strict') || app()->isProduction();
    }

    private function runtimeIdentityMatches(string $expectedRole): bool
    {
        $runtimeConnection = DB::connection();

        if ($runtimeConnection->getDriverName() !== 'pgsql') {
            if ($this->requiresRuntimeRole()) {
                $this->components->error('The runtime database connection must use PostgreSQL in strict provisioning.');

                return false;
            }

            return true;
        }

        $identity = $runtimeConnection->selectOne('select current_user as role');
        $actualRole = is_string($identity->role ?? null) ? $identity->role : null;

        if ($actualRole !== $expectedRole) {
            $this->components->error("Runtime connection resolves to role [{$actualRole}], expected [{$expectedRole}].");

            return false;
        }

        return true;
    }

    private function grantRuntimePrivileges(Connection $connection, string $schema, string $role): void
    {
        $quotedSchema = '"'.$schema.'"';
        $quotedRole = '"'.$role.'"';

        $connection->statement("GRANT USAGE ON SCHEMA {$quotedSchema} TO {$quotedRole}");
        $connection->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA {$quotedSchema} TO {$quotedRole}");
        $connection->statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA {$quotedSchema} TO {$quotedRole}");
        $connection->statement("REVOKE UPDATE ON ALL SEQUENCES IN SCHEMA {$quotedSchema} FROM {$quotedRole}");
        $connection->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$quotedSchema} GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$quotedRole}");
        $connection->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA {$quotedSchema} GRANT USAGE, SELECT ON SEQUENCES TO {$quotedRole}");

        // Migration history is owned and written only by the migration connection.
        // Revoke it after the broad runtime grants so a future provisioning run
        // also repairs privileges on an already-created migrations table.
        $migrationsTableExists = $connection->selectOne('SELECT to_regclass(?) AS table_name', ["{$schema}.migrations"])?->table_name !== null;

        if ($migrationsTableExists) {
            $connection->statement("REVOKE ALL PRIVILEGES ON {$quotedSchema}.migrations FROM {$quotedRole}");
        }

        $migrationsSequenceExists = $connection->selectOne('SELECT to_regclass(?) AS sequence_name', ["{$schema}.migrations_id_seq"])?->sequence_name !== null;

        if ($migrationsSequenceExists) {
            $connection->statement("REVOKE ALL PRIVILEGES ON SEQUENCE {$quotedSchema}.migrations_id_seq FROM {$quotedRole}");
        }

        $auditTableExists = $connection->selectOne('SELECT to_regclass(?) AS table_name', ["{$schema}.audit_events"])?->table_name !== null;

        if ($auditTableExists) {
            $connection->statement("REVOKE UPDATE, DELETE ON {$quotedSchema}.audit_events FROM {$quotedRole}");
            $connection->statement("GRANT SELECT, INSERT ON {$quotedSchema}.audit_events TO {$quotedRole}");
            $connection->statement("REVOKE TRIGGER, TRUNCATE ON {$quotedSchema}.audit_events FROM {$quotedRole}");
            $connection->statement("REVOKE TRIGGER, TRUNCATE ON {$quotedSchema}.audit_events FROM PUBLIC");
        }

        $rolesTable = $connection->selectOne('SELECT to_regclass(?) AS table_name', ["{$schema}.roles"]);
        $rolesTableExists = $rolesTable !== null && $rolesTable->table_name !== null;

        if ($rolesTableExists) {
            $connection->statement("REVOKE TRIGGER, TRUNCATE ON {$quotedSchema}.roles FROM {$quotedRole}");
            $connection->statement("REVOKE TRIGGER, TRUNCATE ON {$quotedSchema}.roles FROM PUBLIC");
        }
    }
}
