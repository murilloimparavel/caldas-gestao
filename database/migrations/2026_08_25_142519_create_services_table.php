<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents');
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE services ADD CONSTRAINT services_status_check CHECK (status IN ('active', 'inactive'))");
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes BETWEEN 1 AND 1440)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
