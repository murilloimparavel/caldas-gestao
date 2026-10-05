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
        Schema::create('integration_oauth_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('auth_code_id', 80)->nullable()->unique();
            $table->char('passport_token_id', 80)->nullable()->unique();
            $table->char('passport_refresh_token_id', 80)->nullable()->unique();
            $table->foreignUuid('client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('unit_id')->nullable();
            $table->string('resource', 2048);
            $table->json('capabilities');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('auth_code_id')
                ->references('id')
                ->on('oauth_auth_codes')
                ->nullOnDelete();
            $table->foreign('passport_token_id')
                ->references('id')
                ->on('oauth_access_tokens')
                ->nullOnDelete();
            $table->foreign('passport_refresh_token_id')
                ->references('id')
                ->on('oauth_refresh_tokens')
                ->nullOnDelete();
            $table->foreign(['tenant_id', 'unit_id'])
                ->references(['tenant_id', 'id'])
                ->on('units')
                ->restrictOnDelete();
            $table->index(['client_id', 'user_id', 'revoked_at'], 'integration_oauth_grants_client_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_oauth_grants');
    }
};
