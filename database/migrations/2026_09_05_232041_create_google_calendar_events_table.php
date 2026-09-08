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
        Schema::create('google_calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('connection_id');
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('appointment_id');
            $table->string('google_event_id')->nullable();
            $table->string('sync_status', 24)->default('pending');
            $table->string('operation', 24)->default('upsert');
            $table->unsignedBigInteger('appointment_lock_version')->default(0);
            $table->string('payload_hash', 128)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['connection_id', 'appointment_id']);
            $table->unique(['connection_id', 'google_event_id']);
            $table->index(['tenant_id', 'unit_id', 'sync_status']);
            $table->foreign('connection_id')->references('id')->on('google_calendar_connections')->cascadeOnDelete();
            $table->foreign(['connection_id', 'tenant_id', 'unit_id'])
                ->references(['id', 'tenant_id', 'unit_id'])->on('google_calendar_connections')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'appointment_id'])
                ->references(['tenant_id', 'unit_id', 'id'])->on('appointments')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
    }
};
