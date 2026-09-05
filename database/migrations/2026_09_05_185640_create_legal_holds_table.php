<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('resource_type', 120)->nullable();
            $table->string('resource_id', 120)->nullable();
            $table->string('reason', 500);
            $table->string('reference', 160)->nullable();
            $table->uuid('placed_by_user_id');
            $table->uuid('released_by_user_id')->nullable();
            $table->timestampTz('placed_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'unit_id', 'released_at']);
            $table->index(['tenant_id', 'customer_id', 'released_at']);
            $table->index(['tenant_id', 'resource_type', 'resource_id', 'released_at'], 'legal_holds_resource_idx');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign('placed_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('released_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX legal_holds_active_customer_reason_unique ON legal_holds (tenant_id, unit_id, customer_id, reason) WHERE released_at IS NULL AND customer_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX legal_holds_active_resource_reason_unique ON legal_holds (tenant_id, unit_id, resource_type, resource_id, reason) WHERE released_at IS NULL AND customer_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS legal_holds_active_customer_reason_unique');
            DB::statement('DROP INDEX IF EXISTS legal_holds_active_resource_reason_unique');
        }

        Schema::dropIfExists('legal_holds');
    }
};
