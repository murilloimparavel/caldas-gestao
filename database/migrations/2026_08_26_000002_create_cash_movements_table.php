<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('cash_shift_id');
            $table->string('type', 32);
            $table->integer('amount_cents');
            $table->string('reason', 255);
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->uuid('user_id');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'cash_shift_id', 'created_at']);
            $table->index(['tenant_id', 'unit_id', 'type', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'cash_shift_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('cash_shifts')
                ->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_type_check CHECK (type IN ('supply', 'bleed', 'sale_inflow', 'commission_outflow', 'expense_outflow'))");
            DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT cash_movements_amount_check CHECK (amount_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
