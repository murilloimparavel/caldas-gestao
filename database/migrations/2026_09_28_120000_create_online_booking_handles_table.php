<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_booking_handles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('handle', 100)->unique();
            $table->string('status', 16)->default('reserved');
            $table->timestampTz('redirect_until')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->index(['status', 'redirect_until']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_booking_handles');
    }
};
