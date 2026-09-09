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
        Schema::create('online_booking_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('site_id');
            $table->uuid('public_domain_id')->nullable();
            $table->unsignedBigInteger('version');
            $table->unsignedBigInteger('source_revision');
            $table->jsonb('content');
            $table->char('content_hash', 64);
            $table->string('template_key', 64);
            $table->string('public_slug', 100);
            $table->uuid('published_by');
            $table->timestampTz('published_at');
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();
            $table->unique(['site_id', 'version']);
            $table->index(['tenant_id', 'unit_id', 'published_at']);
            $table->index(['public_slug', 'superseded_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('online_booking_sites')->cascadeOnDelete();
            $table->foreign('public_domain_id')->references('id')->on('tenant_domains')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('online_booking_sites', function (Blueprint $table): void {
            $table->foreign('active_publication_id')->references('id')->on('online_booking_publications')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_booking_sites', function (Blueprint $table): void {
            $table->dropForeign(['active_publication_id']);
        });

        Schema::dropIfExists('online_booking_publications');
    }
};
