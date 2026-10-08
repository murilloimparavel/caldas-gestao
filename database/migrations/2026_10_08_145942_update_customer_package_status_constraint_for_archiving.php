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
            $table->string('archived_from_status')->nullable()->after('status');
            $table->timestampTz('archived_at')->nullable()->after('archived_from_status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('pending', 'active', 'exhausted', 'completed', 'expired', 'cancelled', 'archived'))");
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_archive_metadata_check CHECK ((status = 'archived' AND archived_from_status IN ('completed', 'exhausted', 'cancelled', 'expired') AND archived_at IS NOT NULL) OR (status <> 'archived' AND archived_from_status IS NULL AND archived_at IS NULL))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('customer_packages')->where('status', 'archived')->exists()) {
            throw new RuntimeException('Cannot roll back package archiving while archived packages exist. Restore them before retrying.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_archive_metadata_check');
            DB::statement('ALTER TABLE customer_packages DROP CONSTRAINT IF EXISTS customer_packages_status_check');
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('pending', 'active', 'exhausted', 'completed', 'expired', 'cancelled'))");
        }

        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropColumn(['archived_from_status', 'archived_at']);
        });
    }
};
