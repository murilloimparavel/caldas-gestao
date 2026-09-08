<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_status_check');
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('draft', 'open', 'ready_to_bill', 'completed', 'finalized', 'cancelled', 'adjusted'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_status_check');
            DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('draft', 'open', 'ready_to_bill', 'completed', 'finalized', 'cancelled'))");
        }
    }
};
