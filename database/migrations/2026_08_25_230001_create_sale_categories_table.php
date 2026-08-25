<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name', 160);
            $table->string('key', 64);
            $table->string('type', 24)->default('service');
            $table->string('uniqueness_scope', 24)->default('none');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'key']);
            $table->index(['tenant_id', 'unit_id', 'type', 'is_active', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE sale_categories ADD CONSTRAINT sale_categories_type_check CHECK (type IN ('service', 'product', 'mixed'))");
            DB::statement("ALTER TABLE sale_categories ADD CONSTRAINT sale_categories_uniqueness_scope_check CHECK (uniqueness_scope IN ('customer', 'appointment', 'reference', 'none'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_categories');
    }
};
