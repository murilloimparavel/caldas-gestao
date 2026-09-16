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
            $table->string('scope', 20)->default('all')->after('product_id');
            $table->index(['tenant_id', 'unit_id', 'professional_id', 'scope']);
        });

        DB::table('commission_rules')
            ->whereNotNull('service_id')
            ->update(['scope' => 'service']);
        DB::table('commission_rules')
            ->whereNotNull('product_id')
            ->update(['scope' => 'product']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table): void {
            $table->dropIndex('commission_rules_tenant_id_unit_id_professional_id_scope_index');
            $table->dropColumn('scope');
        });
    }
};
