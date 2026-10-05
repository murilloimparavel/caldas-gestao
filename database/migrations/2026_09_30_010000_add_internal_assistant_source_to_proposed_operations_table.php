<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->string('source', 32)->default('credential')->after('oauth_grant_id');
            $table->unique(['source', 'actor_id', 'idempotency_key_hash'], 'proposed_operations_internal_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::table('proposed_operations', function (Blueprint $table): void {
            $table->dropUnique('proposed_operations_internal_idempotency_uq');
            $table->dropColumn('source');
        });
    }
};
