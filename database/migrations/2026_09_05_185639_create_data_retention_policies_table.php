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
        Schema::create('data_retention_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id')->nullable();
            $table->string('data_class', 64);
            $table->unsignedInteger('retention_days');
            $table->string('anchor', 32)->default('last_activity_at');
            $table->boolean('enabled')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'data_class']);
            $table->index(['tenant_id', 'unit_id', 'enabled']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE data_retention_policies ADD CONSTRAINT data_retention_policies_data_class_check CHECK (data_class IN ('customer'))");
            DB::statement("ALTER TABLE data_retention_policies ADD CONSTRAINT data_retention_policies_anchor_check CHECK (anchor IN ('last_activity_at', 'created_at'))");
            DB::statement('ALTER TABLE data_retention_policies ADD CONSTRAINT data_retention_policies_retention_days_check CHECK (retention_days >= 1)');
        }

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX data_retention_policies_tenant_default_data_class_unique ON data_retention_policies (tenant_id, data_class) WHERE unit_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS data_retention_policies_tenant_default_data_class_unique');
        }

        Schema::dropIfExists('data_retention_policies');
    }
};
