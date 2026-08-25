<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->uuid('actor_user_id')->nullable();
            $table->string('key', 200);
            $table->char('request_hash', 64);
            $table->string('status', 24)->default('started');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('resource_type', 120)->nullable();
            $table->uuid('resource_id')->nullable();
            $table->jsonb('response_ref')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX idempotency_keys_scope_unique ON idempotency_keys (tenant_id, actor_user_id, key) NULLS NOT DISTINCT');
            DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_status_check CHECK (status IN ('started', 'succeeded', 'failed'))");
            DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_completion_check CHECK ((status = 'started' AND completed_at IS NULL) OR (status IN ('succeeded', 'failed') AND completed_at IS NOT NULL))");
            DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_response_check CHECK ((status = 'started' AND response_code IS NULL AND response_ref IS NULL) OR (status IN ('succeeded', 'failed') AND response_code IS NOT NULL AND response_ref IS NOT NULL))");
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX idempotency_keys_scope_actor_unique ON idempotency_keys (tenant_id, actor_user_id, key) WHERE actor_user_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX idempotency_keys_scope_tenant_unique ON idempotency_keys (tenant_id, key) WHERE actor_user_id IS NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idempotency_keys_scope_unique');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS idempotency_keys_scope_actor_unique');
            DB::statement('DROP INDEX IF EXISTS idempotency_keys_scope_tenant_unique');
        }

        Schema::dropIfExists('idempotency_keys');
    }
};
