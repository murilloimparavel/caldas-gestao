<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_subscription_id');
            $table->uuid('source_cycle_id');
            $table->uuid('target_cycle_id')->nullable();
            $table->string('idempotency_key', 255);
            $table->string('status', 20)->default('pending');
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('attempted_at');
            $table->timestampTz('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'customer_subscription_id', 'idempotency_key']);
            $table->index(['tenant_id', 'unit_id', 'status', 'attempted_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_subscription_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customer_subscriptions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'source_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'target_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_renewal_attempts ADD CONSTRAINT subscription_renewal_attempts_status_check CHECK (status IN ('pending', 'succeeded', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_attempts');
    }
};
