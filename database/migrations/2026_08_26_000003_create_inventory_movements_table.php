<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('product_id');
            $table->string('type', 32);
            $table->integer('quantity');
            $table->integer('unit_cost_cents')->default(0);
            $table->integer('previous_stock');
            $table->integer('resulting_stock');
            $table->string('reason', 255);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->uuid('user_id');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'product_id', 'created_at']);
            $table->index(['tenant_id', 'unit_id', 'type', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'product_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('sale_outflow', 'purchase_inflow', 'adjustment_loss', 'adjustment_gain', 'manual_count'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
