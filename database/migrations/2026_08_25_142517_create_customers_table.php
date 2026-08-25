<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name', 160);
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status', 'name']);
            $table->index(['tenant_id', 'unit_id', 'phone']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('active', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
