<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id');
            $table->uuid('subscription_plan_id');
            $table->string('status', 20)->default('active');
            $table->date('start_date');
            $table->date('next_billing_date')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->index(['tenant_id', 'subscription_plan_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'subscription_plan_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('subscription_plans')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customer_subscriptions ADD CONSTRAINT customer_subscriptions_status_check CHECK (status IN ('active', 'paused', 'cancelled', 'expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
