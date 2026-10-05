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
            $table->uuid('customer_package_id')->nullable()->after('customer_id');
            $table->unique('customer_package_id', 'financial_obligations_customer_package_unique');
            $table->foreign('customer_package_id')
                ->references('id')
                ->on('customer_packages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_obligations', function (Blueprint $table): void {
            $table->dropForeign(['customer_package_id']);
            $table->dropUnique('financial_obligations_customer_package_unique');
            $table->dropColumn('customer_package_id');
        });
    }
};
