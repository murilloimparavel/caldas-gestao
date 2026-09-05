<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('retention_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('retention_campaign_id');
            $table->uuid('retention_campaign_recipient_id');
            $table->uuid('customer_id');
            $table->string('channel', 24);
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 240);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key'], 'retention_deliveries_idempotency_unique');
            $table->unique('retention_campaign_recipient_id');
            $table->index(['tenant_id', 'unit_id', 'status', 'available_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'retention_campaign_id'])->references(['tenant_id', 'id'])->on('retention_campaigns')->cascadeOnDelete();
            $table->foreign('retention_campaign_recipient_id')->references('id')->on('retention_campaign_recipients')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customers')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retention_deliveries ADD CONSTRAINT retention_deliveries_status_check CHECK (status IN ('pending', 'blocked', 'failed', 'sent'))");
            DB::statement("ALTER TABLE retention_deliveries ADD CONSTRAINT retention_deliveries_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'phone'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE retention_deliveries DROP CONSTRAINT IF EXISTS retention_deliveries_status_check');
            DB::statement('ALTER TABLE retention_deliveries DROP CONSTRAINT IF EXISTS retention_deliveries_channel_check');
        }
        Schema::dropIfExists('retention_deliveries');
    }
};
