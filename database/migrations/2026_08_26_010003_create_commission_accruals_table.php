<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_accruals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('professional_id');
            $table->uuid('sale_id');
            $table->uuid('sale_item_id');
            $table->string('item_name_snapshot', 160);
            $table->integer('gross_amount_cents');
            $table->string('rate_type', 20);
            $table->integer('rate_value');
            $table->integer('commission_amount_cents');
            $table->string('status', 20)->default('accrued');
            $table->timestampTz('settled_at')->nullable();
            $table->uuid('settlement_id')->nullable();
            $table->integer('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'professional_id', 'status']);
            $table->index(['tenant_id', 'unit_id', 'sale_id']);
            $table->index(['tenant_id', 'unit_id', 'sale_item_id']);
            $table->index(['tenant_id', 'unit_id', 'settlement_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sales')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_item_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sale_items')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'settlement_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('commission_settlements')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE commission_accruals ADD CONSTRAINT commission_accruals_status_check CHECK (status IN ('accrued', 'settled', 'cancelled'))");
            DB::statement("ALTER TABLE commission_accruals ADD CONSTRAINT commission_accruals_rate_type_check CHECK (rate_type IN ('percentage', 'fixed'))");
            DB::statement('ALTER TABLE commission_accruals ADD CONSTRAINT commission_accruals_gross_amount_check CHECK (gross_amount_cents >= 0)');
            DB::statement('ALTER TABLE commission_accruals ADD CONSTRAINT commission_accruals_commission_amount_check CHECK (commission_amount_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_accruals');
    }
};
