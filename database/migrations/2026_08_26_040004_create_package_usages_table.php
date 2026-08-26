<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_usages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_package_id');
            $table->uuid('sale_id')->nullable();
            $table->uuid('sale_item_id')->nullable();
            $table->integer('sessions_consumed')->default(1);
            $table->uuid('user_id');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'customer_package_id']);
            $table->index(['tenant_id', 'unit_id', 'user_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_package_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customer_packages')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sales')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_item_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sale_items')
                ->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE package_usages ADD CONSTRAINT package_usages_sessions_consumed_check CHECK (sessions_consumed > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_usages');
    }
};
