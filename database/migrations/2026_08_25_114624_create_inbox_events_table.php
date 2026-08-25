<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->string('consumer', 120);
            $table->uuid('event_id');
            $table->string('event_type', 160);
            $table->unsignedSmallInteger('event_version');
            $table->string('correlation_id', 120)->nullable();
            $table->string('causation_id', 120)->nullable();
            $table->jsonb('payload')->default('{}');
            $table->string('status', 24)->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('available_at');
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('dead_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->string('locked_by', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['consumer', 'event_id']);
            $table->index(['tenant_id', 'status', 'available_at', 'lease_until']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inbox_events ADD CONSTRAINT inbox_events_status_check CHECK (status IN ('received', 'processing', 'retryable', 'processed', 'failed', 'dead'))");
            DB::statement("ALTER TABLE inbox_events ADD CONSTRAINT inbox_events_processing_lease_check CHECK (status <> 'processing' OR (locked_by IS NOT NULL AND lease_until IS NOT NULL))");
            DB::statement("ALTER TABLE inbox_events ADD CONSTRAINT inbox_events_processed_check CHECK ((status = 'processed') = (processed_at IS NOT NULL))");
            DB::statement("ALTER TABLE inbox_events ADD CONSTRAINT inbox_events_dead_check CHECK ((status = 'dead') = (dead_at IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_events');
    }
};
