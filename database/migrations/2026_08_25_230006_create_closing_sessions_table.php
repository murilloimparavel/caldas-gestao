<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('closing_subject', 255);
            $table->string('currency', 3)->default('BRL');
            $table->integer('expected_total_cents');
            $table->integer('final_total_cents')->default(0);
            $table->string('status', 32)->default('draft');
            $table->string('receipt_number', 64)->nullable();
            $table->jsonb('receipt_payload')->nullable();
            $table->string('idempotency_key', 200)->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status', 'closing_subject']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE closing_sessions ADD CONSTRAINT closing_sessions_status_check CHECK (status IN ('draft', 'ready', 'processing', 'completed', 'cancelled', 'failed'))");
            DB::statement('ALTER TABLE closing_sessions ADD CONSTRAINT closing_sessions_expected_total_check CHECK (expected_total_cents >= 0)');
            DB::statement('ALTER TABLE closing_sessions ADD CONSTRAINT closing_sessions_final_total_check CHECK (final_total_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_sessions');
    }
};
