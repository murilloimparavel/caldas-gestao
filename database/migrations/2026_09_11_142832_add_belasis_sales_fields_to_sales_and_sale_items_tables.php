<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'services_tenant_unit_source_unique');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'products_tenant_unit_source_unique');
        });

        Schema::table('professionals', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'professionals_tenant_unit_source_unique');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->json('source_metadata')->nullable()->after('notes');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'sales_tenant_unit_source_unique');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->json('source_metadata')->nullable()->after('total_cents');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'sale_items_tenant_unit_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropUnique('sale_items_tenant_unit_source_unique');
            $table->dropColumn(['source_id', 'source_metadata']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique('sales_tenant_unit_source_unique');
            $table->dropColumn(['source_id', 'source_metadata']);
        });

        Schema::table('professionals', function (Blueprint $table): void {
            $table->dropUnique('professionals_tenant_unit_source_unique');
            $table->dropColumn('source_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_tenant_unit_source_unique');
            $table->dropColumn('source_id');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('services_tenant_unit_source_unique');
            $table->dropColumn('source_id');
        });
    }
};
