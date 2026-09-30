<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT IF EXISTS cash_movements_type_check');
            DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_check CHECK (type IN ('supply', 'bleed', 'sale_inflow', 'sale_reversal_outflow', 'commission_outflow', 'expense_outflow'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT IF EXISTS cash_movements_type_check');
            DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_check CHECK (type IN ('supply', 'bleed', 'sale_inflow', 'commission_outflow', 'expense_outflow'))");
        }
    }
};
