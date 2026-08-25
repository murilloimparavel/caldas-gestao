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
        Schema::create('availability_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('professional_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('timezone', 64);
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'professional_id', 'weekday', 'starts_at', 'ends_at'], 'availability_rules_slot_unique');
            $table->index(['tenant_id', 'unit_id', 'professional_id', 'weekday', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
            DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_time_check CHECK (ends_at > starts_at)');
            DB::statement("ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_status_check CHECK (status IN ('active', 'inactive'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
