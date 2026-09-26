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
        Schema::create('online_booking_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('public_domain_id')->nullable();
            $table->uuid('active_publication_id')->nullable();
            $table->string('public_slug', 100);
            $table->string('template_key', 64)->default('essential');
            $table->string('status', 32)->default('unpublished');
            $table->unsignedBigInteger('draft_revision')->default(0);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('unpublished_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'unit_id']);
            $table->unique('public_slug');
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('public_domain_id')->references('id')->on('tenant_domains')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_booking_sites');
    }
};
