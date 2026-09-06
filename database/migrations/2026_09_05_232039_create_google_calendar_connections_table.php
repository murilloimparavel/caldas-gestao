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
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->uuid('connected_by_user_id')->nullable();
            $table->string('provider', 32)->default('google');
            $table->string('status', 24)->default('disconnected');
            $table->string('google_account_email')->nullable();
            $table->string('calendar_id')->nullable();
            $table->string('calendar_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->jsonb('scopes')->default('[]');
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'unit_id', 'id']);
            $table->unique(['id', 'tenant_id', 'unit_id']);
            $table->unique(['tenant_id', 'unit_id']);
            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->foreign('connected_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
