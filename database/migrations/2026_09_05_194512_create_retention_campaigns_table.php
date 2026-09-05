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
        Schema::create('retention_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('created_by_user_id')->nullable();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft');
            $table->string('channel', 24);
            $table->string('purpose', 64)->default('marketing');
            $table->jsonb('segment_definition');
            $table->string('subject', 240)->nullable();
            $table->text('message');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('audience_snapshot_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE retention_campaigns ADD CONSTRAINT retention_campaigns_status_check CHECK (status IN ('draft', 'active', 'paused', 'completed'))");
            DB::statement("ALTER TABLE retention_campaigns ADD CONSTRAINT retention_campaigns_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'phone'))");
            DB::statement('ALTER TABLE retention_campaigns ADD CONSTRAINT retention_campaigns_dates_check CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE retention_campaigns DROP CONSTRAINT IF EXISTS retention_campaigns_status_check');
            DB::statement('ALTER TABLE retention_campaigns DROP CONSTRAINT IF EXISTS retention_campaigns_channel_check');
            DB::statement('ALTER TABLE retention_campaigns DROP CONSTRAINT IF EXISTS retention_campaigns_dates_check');
        }
        Schema::dropIfExists('retention_campaigns');
    }
};
