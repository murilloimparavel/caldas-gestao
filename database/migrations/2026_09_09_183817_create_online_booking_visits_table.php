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
        Schema::create('online_booking_visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('publication_id')->nullable();
            $table->uuid('campaign_link_id')->nullable();
            $table->char('visitor_hash', 64);
            $table->string('landing_path', 500);
            $table->string('referer_host', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->boolean('consent')->default(false);
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('publication_id')->references('id')->on('online_booking_publications')->nullOnDelete();
            $table->foreign('campaign_link_id')->references('id')->on('online_booking_campaign_links')->nullOnDelete();
            $table->index(['tenant_id', 'unit_id', 'occurred_at']);
            $table->index(['campaign_link_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_booking_visits');
    }
};
