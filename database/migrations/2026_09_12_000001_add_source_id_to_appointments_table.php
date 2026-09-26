<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('source_id', 191)->nullable()->after('id');
            $table->unique(['tenant_id', 'unit_id', 'source_id'], 'appointments_tenant_unit_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique('appointments_tenant_unit_source_unique');
            $table->dropColumn('source_id');
        });
    }
};
