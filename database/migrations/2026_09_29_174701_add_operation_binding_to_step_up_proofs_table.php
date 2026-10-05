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
        Schema::table('step_up_proofs', function (Blueprint $table): void {
            $table->uuid('target_id')->nullable()->after('purpose');
            $table->char('command_hash', 64)->nullable()->after('target_id');
            $table->index(['purpose', 'target_id', 'command_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('step_up_proofs', function (Blueprint $table): void {
            $table->dropIndex(['purpose', 'target_id', 'command_hash']);
            $table->dropColumn(['target_id', 'command_hash']);
        });
    }
};
