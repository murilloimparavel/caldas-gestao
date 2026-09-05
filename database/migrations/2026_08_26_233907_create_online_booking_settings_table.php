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
        Schema::create('online_booking_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('public_slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('whatsapp_phone', 40)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('instagram_url', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->string('brand_color', 20)->default('#2563eb');
            $table->string('booking_flow', 32)->default('service_first');
            $table->unsignedInteger('minimum_notice_minutes')->default(0);
            $table->jsonb('public_hours')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'unit_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('online_booking_settings');
    }
};
