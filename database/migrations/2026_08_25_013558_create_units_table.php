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
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('slug', 80);
            $table->string('name', 160);
            $table->string('status', 24)->default('active');
            $table->string('timezone', 64)->nullable();
            $table->jsonb('address')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE units ADD CONSTRAINT units_status_check CHECK (status IN ('active', 'inactive'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
