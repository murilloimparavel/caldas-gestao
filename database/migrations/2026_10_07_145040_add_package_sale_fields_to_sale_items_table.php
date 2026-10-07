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
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->uuid('package_template_id')->nullable()->after('product_id');
            $table->uuid('customer_package_id')->nullable()->after('package_template_id');
            $table->foreign(['tenant_id', 'unit_id', 'package_template_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('package_templates')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_package_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customer_packages')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS sale_items_item_type_check');
            DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_item_type_check CHECK (item_type IN ('service', 'product', 'package', 'custom'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sale_items DROP CONSTRAINT IF EXISTS sale_items_item_type_check');
            DB::statement("ALTER TABLE sale_items ADD CONSTRAINT sale_items_item_type_check CHECK (item_type IN ('service', 'product', 'custom'))");
        }

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'unit_id', 'package_template_id']);
            $table->dropForeign(['tenant_id', 'unit_id', 'customer_package_id']);
            $table->dropColumn(['package_template_id', 'customer_package_id']);
        });
    }
};
