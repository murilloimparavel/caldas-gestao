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
        Schema::create('customer_communication_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('customer_id');
            $table->string('channel', 24);
            $table->boolean('opted_in')->default(false);
            $table->string('source', 32)->default('staff');
            $table->timestampTz('consented_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'unit_id', 'customer_id', 'channel']);
            $table->index(['tenant_id', 'unit_id', 'opted_in']);
            $table->foreign('tenant_id', 'ccp_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'], 'ccp_unit_fk')->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id', 'customer_id'])
                ->name('ccp_customer_fk')
                ->references(['tenant_id', 'unit_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customer_communication_preferences ADD CONSTRAINT customer_communication_preferences_channel_check CHECK (channel IN ('email', 'sms', 'whatsapp', 'phone'))");
            DB::statement("ALTER TABLE customer_communication_preferences ADD CONSTRAINT customer_communication_preferences_source_check CHECK (source IN ('staff', 'customer', 'import', 'system'))");
            DB::statement('ALTER TABLE customer_communication_preferences ADD CONSTRAINT customer_communication_preferences_consent_check CHECK ((opted_in IS TRUE AND consented_at IS NOT NULL AND revoked_at IS NULL) OR (opted_in IS FALSE AND revoked_at IS NOT NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_communication_preferences DROP CONSTRAINT IF EXISTS customer_communication_preferences_channel_check');
            DB::statement('ALTER TABLE customer_communication_preferences DROP CONSTRAINT IF EXISTS customer_communication_preferences_source_check');
            DB::statement('ALTER TABLE customer_communication_preferences DROP CONSTRAINT IF EXISTS customer_communication_preferences_consent_check');
        }
        Schema::dropIfExists('customer_communication_preferences');
    }
};
