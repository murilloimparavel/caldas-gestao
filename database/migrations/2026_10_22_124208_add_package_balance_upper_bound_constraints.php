<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Historical imports can violate these limits. NOT VALID preserves them
        // while enforcing the limits for new writes and updates.
        DB::statement('ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_remaining_sessions_upper_check CHECK (remaining_sessions <= total_sessions) NOT VALID');
        DB::statement('ALTER TABLE customer_package_services ADD CONSTRAINT customer_package_services_remaining_quantity_upper_check CHECK (allocated_quantity >= 0 AND remaining_quantity >= 0 AND remaining_quantity <= allocated_quantity) NOT VALID');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE customer_package_services DROP CONSTRAINT IF EXISTS customer_package_services_remaining_quantity_upper_check');
        DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_remaining_sessions_upper_check');
    }
};
