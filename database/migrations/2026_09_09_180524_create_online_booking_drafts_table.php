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
        Schema::create('online_booking_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('site_id');
            $table->unsignedBigInteger('revision')->default(1);
            $table->jsonb('content');
            $table->char('content_hash', 64);
            $table->uuid('updated_by');
            $table->timestampsTz();
            $table->unique('site_id');
            $table->unique(['site_id', 'revision']);
            $table->index(['tenant_id', 'unit_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('online_booking_sites')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_booking_drafts');
    }
};
