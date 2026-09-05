<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_usage_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_subscription_id');
            $table->uuid('subscription_cycle_id');
            $table->uuid('subscription_cycle_usage_id');
            $table->uuid('service_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('idempotency_key', 255)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->json('payload_metadata')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->timestampTz('consumed_at');
            $table->timestampTz('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'subscription_cycle_id', 'idempotency_key']);
            $table->index(['tenant_id', 'unit_id', 'customer_subscription_id', 'service_id']);
            $table->index(['tenant_id', 'subscription_cycle_id', 'reversed_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_subscription_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customer_subscriptions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'subscription_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'subscription_cycle_usage_id'])->references(['tenant_id', 'id'])->on('subscription_cycle_usages')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])->references(['tenant_id', 'unit_id', 'id'])->on('services')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_usage_entries ADD CONSTRAINT subscription_usage_entries_quantity_check CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_usage_entries');
    }
};
