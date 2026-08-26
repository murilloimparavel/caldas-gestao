<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id');
            $table->uuid('package_template_id');
            $table->uuid('sale_id')->nullable();
            $table->integer('total_sessions');
            $table->integer('remaining_sessions');
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->index(['tenant_id', 'package_template_id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'package_template_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('package_templates')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sales')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_status_check CHECK (status IN ('active', 'exhausted', 'expired', 'cancelled'))");
            DB::statement('ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_total_sessions_check CHECK (total_sessions > 0)');
            DB::statement('ALTER TABLE customer_packages ADD CONSTRAINT customer_packages_remaining_sessions_check CHECK (remaining_sessions >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_packages');
    }
};
