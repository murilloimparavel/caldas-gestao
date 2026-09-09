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
        Schema::table('financial_obligations', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->json('source_metadata')->nullable()->after('notes');

            $table->unique(
                ['tenant_id', 'unit_id', 'source_id'],
                'financial_obligations_tenant_unit_source_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_obligations', function (Blueprint $table): void {
            $table->dropUnique('financial_obligations_tenant_unit_source_unique');
            $table->dropColumn(['source_id', 'source_metadata']);
        });
    }
};
