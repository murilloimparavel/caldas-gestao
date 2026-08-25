<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_service', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('professional_id');
            $table->uuid('service_id');
            $table->timestampsTz();

            $table->primary(['professional_id', 'service_id']);
            $table->unique(['tenant_id', 'unit_id', 'professional_id', 'service_id']);
            $table->index(['tenant_id', 'unit_id', 'service_id']);
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'service_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_service');
    }
};
