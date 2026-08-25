<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('appointment_id');
            $table->uuid('service_id');
            $table->uuid('professional_id');
            $table->string('service_name_snapshot', 160);
            $table->unsignedInteger('duration_minutes');
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3)->default('BRL');
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'appointment_id', 'position'], 'appointment_items_position_unique');
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->index(['tenant_id', 'unit_id', 'professional_id']);
            $table->foreign(['tenant_id', 'unit_id', 'appointment_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('appointments')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};
