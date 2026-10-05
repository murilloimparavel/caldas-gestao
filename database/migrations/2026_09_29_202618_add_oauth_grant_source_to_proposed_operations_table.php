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
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->foreignUuid('credential_id')->nullable()->change();
            $table->foreignUuid('oauth_grant_id')->nullable()->after('credential_id');
            $table->foreign('oauth_grant_id')
                ->references('id')
                ->on('integration_oauth_grants')
                ->restrictOnDelete();
            $table->unique(['oauth_grant_id', 'idempotency_key_hash'], 'proposed_operations_oauth_idempotency_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->dropUnique('proposed_operations_oauth_idempotency_uq');
            $table->dropForeign(['oauth_grant_id']);
            $table->dropColumn('oauth_grant_id');
            $table->foreignUuid('credential_id')->nullable(false)->change();
        });
    }
};
