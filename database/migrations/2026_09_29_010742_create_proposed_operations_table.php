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
        Schema::create('proposed_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('credential_id')->constrained('integration_credentials')->restrictOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('unit_id');
            $table->string('operation_key', 100);
            $table->json('input');
            $table->char('input_hash', 64);
            $table->char('idempotency_key_hash', 64);
            $table->string('status', 20)->default('pending_confirmation');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('result_reference')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'unit_id'])->references(['tenant_id', 'id'])->on('units')->restrictOnDelete();
            $table->unique(['credential_id', 'idempotency_key_hash'], 'proposed_operations_idempotency_uq');
            $table->index(['actor_id', 'tenant_id', 'unit_id', 'status'], 'proposed_operations_context_idx');
            $table->index(['status', 'expires_at'], 'proposed_operations_expiry_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposed_operations');
    }
};
