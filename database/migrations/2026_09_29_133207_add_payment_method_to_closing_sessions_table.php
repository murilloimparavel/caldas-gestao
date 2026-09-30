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
        Schema::table('closing_sessions', function (Blueprint $table): void {
            $table->string('payment_method', 20)->nullable()->after('currency');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE closing_sessions ADD CONSTRAINT closing_sessions_payment_method_check CHECK (payment_method IS NULL OR payment_method IN ('pix', 'debit_card', 'credit_card', 'cash', 'permuta'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE closing_sessions DROP CONSTRAINT IF EXISTS closing_sessions_payment_method_check');
        }

        Schema::table('closing_sessions', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }
};
