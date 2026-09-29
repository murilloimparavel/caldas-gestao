<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closing_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('cash_received_cents')->nullable()->after('payment_method');
            $table->unsignedBigInteger('cash_change_cents')->nullable()->after('cash_received_cents');
        });
    }

    public function down(): void
    {
        Schema::table('closing_sessions', function (Blueprint $table): void {
            $table->dropColumn(['cash_received_cents', 'cash_change_cents']);
        });
    }
};
