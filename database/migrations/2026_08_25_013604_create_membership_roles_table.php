<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('membership_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('membership_id');
            $table->uuid('role_id');
            $table->string('scope_kind', 16);
            $table->string('assignment_scope', 120);
            $table->uuid('unit_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'scope_kind', 'unit_id', 'membership_id']);
            $table->index(['membership_id', 'revoked_at']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'membership_id'])
                ->references(['tenant_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'role_id'])
                ->references(['tenant_id', 'id'])
                ->on('roles')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX membership_roles_active_unique ON membership_roles (tenant_id, membership_id, role_id, unit_id) NULLS NOT DISTINCT WHERE revoked_at IS NULL');
            DB::statement("ALTER TABLE membership_roles ADD CONSTRAINT membership_roles_scope_check CHECK ((scope_kind = 'tenant' AND unit_id IS NULL AND assignment_scope = 'tenant') OR (scope_kind = 'unit' AND unit_id IS NOT NULL AND assignment_scope = 'unit:' || unit_id::text))");
            DB::statement("ALTER TABLE membership_roles ADD CONSTRAINT membership_roles_scope_kind_check CHECK (scope_kind IN ('tenant', 'unit'))");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX membership_roles_active_tenant_unique ON membership_roles (tenant_id, membership_id, role_id) WHERE revoked_at IS NULL AND unit_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX membership_roles_active_unit_unique ON membership_roles (tenant_id, membership_id, role_id, unit_id) WHERE revoked_at IS NULL AND unit_id IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS membership_roles_active_unique');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS membership_roles_active_tenant_unique');
            DB::statement('DROP INDEX IF EXISTS membership_roles_active_unit_unique');
        }

        Schema::dropIfExists('membership_roles');
    }
};
