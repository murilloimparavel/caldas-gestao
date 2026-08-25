<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('opened_by_user_id');
            $table->uuid('closed_by_user_id')->nullable();
            $table->integer('initial_amount_cents')->default(0);
            $table->integer('expected_amount_cents')->default(0);
            $table->integer('final_amount_cents')->nullable();
            $table->integer('difference_cents')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status', 'opened_at']);
            $table->index(['tenant_id', 'unit_id', 'opened_by_user_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('opened_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cash_shifts ADD CONSTRAINT cash_shifts_status_check CHECK (status IN ('open', 'closed'))");
            DB::statement('ALTER TABLE cash_shifts ADD CONSTRAINT cash_shifts_initial_amount_check CHECK (initial_amount_cents >= 0)');
            DB::statement('ALTER TABLE cash_shifts ADD CONSTRAINT cash_shifts_expected_amount_check CHECK (expected_amount_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
