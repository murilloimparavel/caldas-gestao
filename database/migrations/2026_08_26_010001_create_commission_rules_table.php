<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('professional_id')->nullable();
            $table->uuid('service_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->string('type', 20)->default('percentage');
            $table->integer('value_rate')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'professional_id']);
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->index(['tenant_id', 'unit_id', 'product_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'product_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('products')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_type_check CHECK (type IN ('percentage', 'fixed'))");
            DB::statement('ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_value_rate_check CHECK (value_rate >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
