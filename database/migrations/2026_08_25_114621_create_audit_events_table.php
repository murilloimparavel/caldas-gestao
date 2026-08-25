<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('event_id')->unique();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('unit_id')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('action', 120);
            $table->string('resource_type', 120);
            $table->uuid('resource_id')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->string('reason', 500)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->ipAddress('ip_address')->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'resource_type', 'resource_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE audit_events ADD CONSTRAINT audit_events_scope_check CHECK (unit_id IS NULL OR tenant_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
