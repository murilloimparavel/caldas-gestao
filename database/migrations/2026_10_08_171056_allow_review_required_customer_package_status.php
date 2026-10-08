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
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('pending', 'active', 'exhausted', 'completed', 'expired', 'cancelled', 'archived', 'review_required'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::table('customer_packages')->where('status', 'review_required')->exists()) {
                throw new RuntimeException('Cannot remove review_required while packages in this status exist. Reconcile them before retrying.');
            }

            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('pending', 'active', 'exhausted', 'completed', 'expired', 'cancelled', 'archived'))");
        }
    }
};
