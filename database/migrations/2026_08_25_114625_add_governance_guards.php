<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $triggerExecution = (int) DB::scalar('SHOW server_version_num') >= 110000
            ? 'EXECUTE FUNCTION'
            : 'EXECUTE PROCEDURE';

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION audit_events_append_only_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_events is append-only; % is not permitted', TG_OP
                    USING ERRCODE = '42501';
            END;
            $$;

            DROP TRIGGER IF EXISTS audit_events_append_only_guard ON audit_events;
            CREATE TRIGGER audit_events_append_only_guard
            BEFORE UPDATE OR DELETE ON audit_events
            FOR EACH ROW {$triggerExecution} audit_events_append_only_guard();

            CREATE OR REPLACE FUNCTION roles_system_immutability_guard()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.is_system THEN
                    RAISE EXCEPTION 'system roles cannot be deleted'
                        USING ERRCODE = '42501';
                END IF;

                IF TG_OP = 'UPDATE' AND (OLD.is_system OR OLD.is_system IS DISTINCT FROM NEW.is_system) THEN
                    RAISE EXCEPTION 'system roles are immutable'
                        USING ERRCODE = '42501';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS roles_system_immutability_guard ON roles;
            CREATE TRIGGER roles_system_immutability_guard
            BEFORE UPDATE OR DELETE ON roles
            FOR EACH ROW {$triggerExecution} roles_system_immutability_guard();
        SQL);

        $runtimeRole = config('database.runtime_role');

        if (is_string($runtimeRole) && preg_match('/\A[a-z][a-z0-9_]*\z/', $runtimeRole) === 1) {
            $quotedRole = '"'.$runtimeRole.'"';
            DB::statement("REVOKE UPDATE, DELETE ON audit_events FROM {$quotedRole}");
            DB::statement("GRANT SELECT, INSERT ON audit_events TO {$quotedRole}");
            DB::statement("REVOKE TRIGGER, TRUNCATE ON audit_events FROM {$quotedRole}");
            DB::statement("REVOKE TRIGGER, TRUNCATE ON roles FROM {$quotedRole}");
            DB::statement('REVOKE UPDATE, DELETE ON audit_events FROM PUBLIC');
            DB::statement('REVOKE TRIGGER, TRUNCATE ON audit_events FROM PUBLIC');
            DB::statement('REVOKE TRIGGER, TRUNCATE ON roles FROM PUBLIC');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_events_append_only_guard ON audit_events;
            DROP FUNCTION IF EXISTS audit_events_append_only_guard();
            DROP TRIGGER IF EXISTS roles_system_immutability_guard ON roles;
            DROP FUNCTION IF EXISTS roles_system_immutability_guard();
        SQL);
    }
};
