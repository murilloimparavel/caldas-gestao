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
        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->nullable()->after('gross_amount_cents');
            $table->unsignedInteger('covered_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('commissionable_quantity')->nullable()->after('covered_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->dropColumn(['quantity', 'covered_quantity', 'commissionable_quantity']);
        });
    }
};
