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
        Schema::create('step_up_proofs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->nullOnDelete();
            $table->uuid('tenant_id');
            $table->uuid('unit_id')->nullable();
            $table->foreignId('passkey_id')->nullable()->constrained('passkeys')->nullOnDelete();
            $table->string('factor', 32)->default('passkey');
            $table->string('purpose', 64);
            $table->timestampTz('verified_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'purpose', 'verified_at']);
            $table->index(['user_id', 'verified_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('step_up_proofs');
    }
};
