<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('sale_id');
            $table->string('item_type', 24)->default('custom');
            $table->uuid('service_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->uuid('professional_id')->nullable();
            $table->string('name_snapshot', 160);
            $table->integer('unit_price_cents');
            $table->integer('quantity')->default(1);
            $table->integer('discount_cents')->default(0);
            $table->integer('total_cents');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'sale_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sales')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'product_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('products')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_item_type_check CHECK (item_type IN ('service', 'product', 'custom'))");
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_quantity_check CHECK (quantity > 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_unit_price_check CHECK (unit_price_cents >= 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_discount_check CHECK (discount_cents >= 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_total_check CHECK (total_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
