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
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('hostname', 253)->unique();
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending');
            $table->string('verification_token', 128)->unique();
            $table->string('expected_cname', 253);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->string('ssl_status', 24)->default('pending');
            $table->timestampTz('ssl_verified_at')->nullable();
            $table->timestampTz('last_dns_check_at')->nullable();
            $table->text('last_dns_error')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
            $table->index(['tenant_id', 'kind', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
