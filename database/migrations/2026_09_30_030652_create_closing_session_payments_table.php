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
        Schema::create('closing_session_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('closing_session_id');
            $table->uuid('cash_shift_id')->nullable();
            $table->string('payment_method', 20);
            $table->integer('amount_cents');
            $table->integer('tendered_cents')->nullable();
            $table->integer('change_cents')->default(0);
            $table->uuid('recorded_by_user_id');
            $table->timestampTz('recorded_at');
            $table->uuid('reversal_of_id')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->string('reversal_reason', 1000)->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'closing_session_id']);
            $table->index(['tenant_id', 'unit_id', 'cash_shift_id']);
            $table->unique('reversal_of_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'closing_session_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('closing_sessions')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'cash_shift_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('cash_shifts')
                ->nullOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'reversal_of_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('closing_session_payments')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE closing_session_payments ADD CONSTRAINT closing_session_payments_method_check CHECK (payment_method IN ('pix', 'debit_card', 'credit_card', 'cash', 'permuta'))");
            DB::statement('ALTER TABLE closing_session_payments ADD CONSTRAINT closing_session_payments_amount_check CHECK (amount_cents > 0 AND (tendered_cents IS NULL OR tendered_cents >= amount_cents) AND change_cents >= 0)');
            DB::statement('ALTER TABLE closing_session_payments ADD CONSTRAINT closing_session_payments_reversal_check CHECK ((is_reversal = false AND reversal_of_id IS NULL AND reversal_reason IS NULL) OR (is_reversal = true AND reversal_of_id IS NOT NULL AND reversal_reason IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE closing_session_payments DROP CONSTRAINT IF EXISTS closing_session_payments_method_check');
            DB::statement('ALTER TABLE closing_session_payments DROP CONSTRAINT IF EXISTS closing_session_payments_amount_check');
            DB::statement('ALTER TABLE closing_session_payments DROP CONSTRAINT IF EXISTS closing_session_payments_reversal_check');
        }

        Schema::dropIfExists('closing_session_payments');
    }
};
