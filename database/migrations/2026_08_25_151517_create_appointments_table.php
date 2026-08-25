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
        Schema::create('appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id');
            $table->uuid('professional_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);
            $table->string('status', 32)->default('confirmed');
            $table->string('source', 32)->default('internal');
            $table->string('color', 32)->nullable();
            $table->boolean('reminder_enabled')->default(true);
            $table->boolean('fit_in')->default(false);
            $table->text('notes')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->index(['tenant_id', 'unit_id', 'starts_at', 'ends_at', 'status']);
            $table->index(['tenant_id', 'unit_id', 'professional_id', 'starts_at', 'ends_at', 'status'], 'appointments_professional_window_index');
            $table->index(['tenant_id', 'unit_id', 'customer_id', 'starts_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'professional_id'])
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('professionals')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_time_check CHECK (ends_at > starts_at)');
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('draft', 'scheduled', 'confirmed', 'checked_in', 'in_service', 'completed', 'no_show', 'cancelled'))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_source_check CHECK (source IN ('internal', 'online', 'imported'))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_cancel_check CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL))");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_professional_no_overlap EXCLUDE USING gist (tenant_id WITH =, unit_id WITH =, professional_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status NOT IN ('cancelled', 'no_show'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
