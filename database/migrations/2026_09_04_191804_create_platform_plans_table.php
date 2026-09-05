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
        Schema::create('platform_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->integer('price_cents')->default(0);
            $table->string('billing_cycle', 20)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->jsonb('features')->default('{}');
            $table->jsonb('limits')->default('{}');
            $table->string('lastlink_product_id')->nullable();
            $table->string('lastlink_offer_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE platform_plans ADD CONSTRAINT platform_plans_cycle_check CHECK (billing_cycle IN ('monthly', 'quarterly', 'yearly'))");
            DB::statement('ALTER TABLE platform_plans ADD CONSTRAINT platform_plans_price_check CHECK (price_cents >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_plans');
    }
};
