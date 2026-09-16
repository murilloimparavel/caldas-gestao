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
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->uuid('category_id')->nullable()->after('product_id');
            $table->index(['tenant_id', 'unit_id', 'category_id']);
            $table->foreign(['tenant_id', 'unit_id', 'category_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('categories')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_scope_check CHECK (scope IN ('all', 'service', 'product', 'service_category', 'product_category'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'unit_id', 'category_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'category_id']);
            $table->dropColumn('category_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commission_rules DROP CONSTRAINT IF EXISTS commission_rules_scope_check');
        }
    }
};
