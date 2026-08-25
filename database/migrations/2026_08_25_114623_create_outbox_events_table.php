<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('event_id')->unique();
            $table->uuid('tenant_id');
            $table->uuid('unit_id')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('aggregate_type', 120);
            $table->uuid('aggregate_id');
            $table->unsignedBigInteger('aggregate_version');
            $table->string('event_type', 160);
            $table->unsignedSmallInteger('event_version');
            $table->string('correlation_id', 120)->nullable();
            $table->string('causation_id', 120)->nullable();
            $table->jsonb('payload')->default('{}');
            $table->string('status', 24)->default('pending');
            $table->timestampTz('occurred_at');
            $table->timestampTz('available_at');
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('dead_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('lease_until')->nullable();
            $table->string('locked_by', 120)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('created_at');

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['correlation_id']);
            $table->index(['status', 'lease_until', 'available_at', 'id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_status_check CHECK (status IN ('pending', 'available', 'publishing', 'retryable', 'dead', 'published'))");
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_published_check CHECK ((status = 'published') = (published_at IS NOT NULL))");
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_dead_check CHECK ((status = 'dead') = (dead_at IS NOT NULL))");
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_terminal_check CHECK (status IN ('published', 'dead') OR (published_at IS NULL AND dead_at IS NULL))");
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_lease_check CHECK (status <> 'publishing' OR (locked_by IS NOT NULL AND lease_until IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
