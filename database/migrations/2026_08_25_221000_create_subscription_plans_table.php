<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('price_cents');
            $table->string('billing_cycle', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'is_active']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_price_cents_check CHECK (price_cents >= 0)');
            DB::statement("ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_billing_cycle_check CHECK (billing_cycle IN ('monthly', 'quarterly', 'yearly'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
