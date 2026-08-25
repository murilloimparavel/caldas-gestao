<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_sale_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('appointment_id');
            $table->uuid('sale_id')->unique();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->unique(['tenant_id', 'unit_id', 'sale_id']);
            $table->index(['tenant_id', 'unit_id', 'appointment_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'appointment_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('appointments')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'sale_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('sales')
                ->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_sale_links');
    }
};
