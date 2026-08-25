<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('key', 100);
            $table->string('status', 24)->default('trial');
            $table->unsignedBigInteger('quantity')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('source', 40);
            $table->jsonb('config')->default('{}');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'key', 'starts_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        $activeStatuses = "'trial', 'active', 'grace', 'suspended'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX entitlements_active_unique ON entitlements (tenant_id, key) WHERE status IN ({$activeStatuses})");
            DB::statement("ALTER TABLE entitlements ADD CONSTRAINT entitlements_status_check CHECK (status IN ({$activeStatuses}, 'expired', 'revoked'))");
            DB::statement("ALTER TABLE entitlements ADD CONSTRAINT entitlements_source_check CHECK (source IN ('plan', 'trial', 'manual', 'integration'))");
            DB::statement('ALTER TABLE entitlements ADD CONSTRAINT entitlements_quantity_check CHECK (quantity IS NULL OR quantity >= 0)');
            DB::statement('ALTER TABLE entitlements ADD CONSTRAINT entitlements_dates_check CHECK (ends_at IS NULL OR ends_at > starts_at)');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX entitlements_active_unique ON entitlements (tenant_id, key) WHERE status IN ({$activeStatuses})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS entitlements_active_unique');
        }

        Schema::dropIfExists('entitlements');
    }
};
