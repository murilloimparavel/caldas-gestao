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
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'unit_id', 'customer_id', 'package_template_id', 'status'],
                'customer_packages_duplicate_guard_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropIndex('customer_packages_duplicate_guard_index');
        });
    }
};
