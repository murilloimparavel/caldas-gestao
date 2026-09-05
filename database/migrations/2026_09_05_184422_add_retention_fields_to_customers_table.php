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
            $table->timestampTz('last_activity_at')->nullable()->after('notes');
            $table->string('retention_status', 24)->default('none')->after('last_activity_at');
            $table->timestampTz('retention_marked_at')->nullable()->after('retention_status');
            $table->timestampTz('retention_reactivated_at')->nullable()->after('retention_marked_at');
            $table->index(['tenant_id', 'unit_id', 'retention_status', 'last_activity_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_retention_status_check CHECK (retention_status IN ('none', 'at_risk', 'reactivated'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_retention_status_check');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_tenant_id_unit_id_retention_status_last_activity_at_index');
            $table->dropColumn(['last_activity_at', 'retention_status', 'retention_marked_at', 'retention_reactivated_at']);
        });
    }
};
