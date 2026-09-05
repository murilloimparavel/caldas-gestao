<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestampTz('anonymized_at')->nullable()->after('retention_reactivated_at');
            $table->string('anonymization_version', 32)->nullable()->after('anonymized_at');
            $table->index(['tenant_id', 'unit_id', 'anonymized_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_retention_status_check');
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_retention_status_check CHECK (retention_status IN ('none', 'at_risk', 'reactivated', 'anonymized'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE customers SET retention_status = 'none' WHERE retention_status = 'anonymized'");
            DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_retention_status_check');
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_retention_status_check CHECK (retention_status IN ('none', 'at_risk', 'reactivated'))");
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_tenant_id_unit_id_anonymized_at_index');
            $table->dropColumn(['anonymized_at', 'anonymization_version']);
        });
    }
};
