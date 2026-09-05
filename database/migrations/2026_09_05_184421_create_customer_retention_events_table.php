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
        Schema::create('customer_retention_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id');
            $table->uuid('actor_user_id')->nullable();
            $table->string('event_type', 32);
            $table->string('idempotency_key', 200)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');

            $table->unique(['tenant_id', 'actor_user_id', 'idempotency_key']);
            $table->index(['tenant_id', 'unit_id', 'customer_id', 'event_type', 'occurred_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customer_retention_events ADD CONSTRAINT customer_retention_events_type_check CHECK (event_type IN ('marked_at_risk', 'reactivated'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_retention_events DROP CONSTRAINT IF EXISTS customer_retention_events_type_check');
        }
        Schema::dropIfExists('customer_retention_events');
    }
};
