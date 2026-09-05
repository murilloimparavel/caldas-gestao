<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cycle_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_subscription_id');
            $table->uuid('subscription_cycle_id');
            $table->uuid('service_id');
            $table->integer('max_uses_snapshot')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'subscription_cycle_id', 'service_id']);
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'customer_subscription_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'customer_subscription_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_subscription_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customer_subscriptions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'subscription_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])->references(['tenant_id', 'unit_id', 'id'])->on('services')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_cycle_usages ADD CONSTRAINT subscription_cycle_usages_max_check CHECK (max_uses_snapshot IS NULL OR max_uses_snapshot >= 0)');
            DB::statement('ALTER TABLE subscription_cycle_usages ADD CONSTRAINT subscription_cycle_usages_used_check CHECK (used_count >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cycle_usages');
    }
};
