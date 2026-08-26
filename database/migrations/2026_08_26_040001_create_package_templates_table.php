<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('price_cents');
            $table->integer('total_sessions');
            $table->integer('validity_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'is_active']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE package_templates ADD CONSTRAINT package_templates_price_cents_check CHECK (price_cents >= 0)');
            DB::statement('ALTER TABLE package_templates ADD CONSTRAINT package_templates_total_sessions_check CHECK (total_sessions > 0)');
            DB::statement('ALTER TABLE package_templates ADD CONSTRAINT package_templates_validity_days_check CHECK (validity_days > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_templates');
    }
};
