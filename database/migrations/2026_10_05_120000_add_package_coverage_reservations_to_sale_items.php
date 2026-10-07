<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->unsignedInteger('covered_quantity')->default(0)->after('quantity');
        });

        Schema::create('package_usage_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_package_id');
            $table->uuid('sale_id');
            $table->uuid('sale_item_id');
            $table->uuid('service_id');
            $table->uuid('user_id');
            $table->uuid('package_usage_id')->nullable();
            $table->unsignedInteger('sessions_reserved');
            $table->string('status', 16)->default('reserved');
            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'sale_item_id']);
            $table->unique('package_usage_id');
            $table->index(['tenant_id', 'unit_id', 'customer_package_id', 'status', 'service_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_package_id'])
                ->references(['tenant_id', 'unit_id', 'id'])->on('customer_packages')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])->on('sales')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_item_id'])
                ->references(['tenant_id', 'unit_id', 'id'])->on('sale_items')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])->on('services')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('package_usage_id')->references('id')->on('package_usages')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_covered_quantity_check CHECK (covered_quantity >= 0 AND covered_quantity <= quantity)');
            DB::statement("ALTER TABLE package_usage_reservations ADD CONSTRAINT package_usage_reservations_status_check CHECK (status IN ('reserved', 'released', 'consumed'))");
            DB::statement('ALTER TABLE package_usage_reservations ADD CONSTRAINT package_usage_reservations_sessions_check CHECK (sessions_reserved > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE package_usage_reservations DROP CONSTRAINT IF EXISTS package_usage_reservations_status_check');
            DB::statement('ALTER TABLE package_usage_reservations DROP CONSTRAINT IF EXISTS package_usage_reservations_sessions_check');
            DB::statement('ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS sale_items_covered_quantity_check');
        }

        Schema::dropIfExists('package_usage_reservations');

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn('covered_quantity');
        });
    }
};
