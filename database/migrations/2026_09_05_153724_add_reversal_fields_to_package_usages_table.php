<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_usages', function (Blueprint $table): void {
            $table->timestampTz('reversed_at')->nullable()->after('user_id');
            $table->uuid('reversed_by_user_id')->nullable()->after('reversed_at');
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by_user_id');
            $table->foreign('reversed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'unit_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('package_usages', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by_user_id']);
            $table->dropIndex(['tenant_id', 'unit_id', 'reversed_at']);
            $table->dropColumn(['reversed_at', 'reversed_by_user_id', 'reversal_reason']);
        });
    }
};
