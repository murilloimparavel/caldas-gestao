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
        Schema::table('package_usages', function (Blueprint $table): void {
            $table->uuid('service_id')->nullable()->after('customer_package_id');
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_usages', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'unit_id', 'service_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'service_id']);
            $table->dropColumn('service_id');
        });
    }
};
