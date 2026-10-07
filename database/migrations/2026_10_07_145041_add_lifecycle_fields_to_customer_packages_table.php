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
        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->timestampTz('activated_at')->nullable()->after('expires_at');
            $table->index(['tenant_id', 'unit_id', 'customer_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('pending', 'active', 'exhausted', 'completed', 'expired', 'cancelled'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('active', 'exhausted', 'expired', 'cancelled'))");
        }

        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'unit_id', 'customer_id', 'status']);
            $table->dropColumn('activated_at');
        });
    }
};
