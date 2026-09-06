<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('sale_category_id');
            $table->string('category_key_snapshot', 64)->nullable();
            $table->string('category_name_snapshot', 160)->nullable();
            $table->string('reference_label', 160)->nullable();
            $table->string('open_context_key', 255)->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('BRL');
            $table->integer('total_amount_cents')->default(0);
            $table->integer('discount_amount_cents')->default(0);
            $table->integer('final_amount_cents')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'unit_id', 'customer_id', 'status']);
            $table->index(['tenant_id', 'unit_id', 'sale_category_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_category_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sale_categories')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('draft', 'open', 'ready_to_bill', 'completed', 'finalized', 'cancelled', 'adjusted'))");
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_total_amount_check CHECK (total_amount_cents >= 0)');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_discount_amount_check CHECK (discount_amount_cents >= 0)');
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_final_amount_check CHECK (final_amount_cents >= 0)');
            DB::statement("CREATE UNIQUE INDEX sales_active_uniqueness_scope_unique ON sales (tenant_id, unit_id, sale_category_id, open_context_key) WHERE status IN ('draft', 'open', 'ready_to_bill') AND open_context_key IS NOT NULL AND deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
