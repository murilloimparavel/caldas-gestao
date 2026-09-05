<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_subscription_id');
            $table->unsignedInteger('cycle_number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->uuid('previous_cycle_id')->nullable();
            $table->uuid('next_cycle_id')->nullable();
            $table->string('renewal_key', 255);
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'customer_subscription_id', 'cycle_number']);
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'customer_subscription_id', 'id']);
            $table->unique(['tenant_id', 'customer_subscription_id', 'renewal_key']);
            $table->index(['tenant_id', 'unit_id', 'status', 'ends_on']);
            $table->index(['tenant_id', 'customer_subscription_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_subscription_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customer_subscriptions')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'previous_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->nullOnDelete();
            $table->foreign(['tenant_id', 'next_cycle_id'])->references(['tenant_id', 'id'])->on('subscription_cycles')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_cycles ADD CONSTRAINT subscription_cycles_status_check CHECK (status IN ('open', 'closed'))");
            DB::statement('ALTER TABLE subscription_cycles ADD CONSTRAINT subscription_cycles_dates_check CHECK (ends_on >= starts_on)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_cycles');
    }
};
