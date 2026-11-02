<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->uuid('professional_id')->nullable()->after('user_id');
            $table->index(['tenant_id', 'professional_id']);
            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])
                ->on('professionals')
                ->nullOnDelete();
            $table->unique(['tenant_id', 'professional_id'], 'memberships_tenant_professional_unique');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'professional_id']);
            $table->dropUnique('memberships_tenant_professional_unique');
            $table->dropIndex(['tenant_id', 'professional_id']);
            $table->dropColumn('professional_id');
        });
    }
};
