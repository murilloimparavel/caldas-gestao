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
        Schema::create('retention_campaign_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('retention_campaign_id');
            $table->uuid('customer_id');
            $table->string('channel', 24);
            $table->string('status', 24)->default('selected');
            $table->timestampTz('selected_at');
            $table->timestampTz('consent_snapshot_at')->nullable();
            $table->timestampTz('last_activity_snapshot_at')->nullable();
            $table->string('retention_status_snapshot', 24)->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'retention_campaign_id', 'customer_id', 'channel'], 'retention_campaign_recipient_unique');
            $table->index(['tenant_id', 'unit_id', 'retention_campaign_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'retention_campaign_id'])->references(['tenant_id', 'id'])->on('retention_campaigns')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])->references(['tenant_id', 'unit_id', 'id'])->on('customers')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retention_campaign_recipients ADD CONSTRAINT retention_campaign_recipients_status_check CHECK (status IN ('selected', 'excluded'))");
            DB::statement("ALTER TABLE retention_campaign_recipients ADD CONSTRAINT retention_campaign_recipients_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'phone'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE retention_campaign_recipients DROP CONSTRAINT IF EXISTS retention_campaign_recipients_status_check');
            DB::statement('ALTER TABLE retention_campaign_recipients DROP CONSTRAINT IF EXISTS retention_campaign_recipients_channel_check');
        }
        Schema::dropIfExists('retention_campaign_recipients');
    }
};
