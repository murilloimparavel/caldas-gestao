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
        Schema::table('sales', function (Blueprint $table): void {
            $table->uuid('professional_id')->nullable()->after('unit_id');
            $table->index(['tenant_id', 'unit_id', 'professional_id']);
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'unit_id', 'professional_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'professional_id']);
            $table->dropColumn('professional_id');
        });
    }
};
