<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 160);
            $table->string('sku', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('cost_price_cents')->default(0);
            $table->unsignedInteger('sale_price_cents')->default(0);
            $table->string('unit_of_measure', 16)->default('un');
            $table->integer('min_stock')->default(0);
            $table->integer('current_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'is_active', 'name']);
            $table->index(['tenant_id', 'unit_id', 'category_id']);
            $table->index(['tenant_id', 'unit_id', 'sku']);
            $table->index(['tenant_id', 'unit_id', 'barcode']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
