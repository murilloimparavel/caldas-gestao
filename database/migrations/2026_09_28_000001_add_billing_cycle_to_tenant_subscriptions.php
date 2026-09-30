<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->string('billing_cycle', 20)->nullable()->after('status');
            $table->index(['tenant_id', 'billing_cycle']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_cycle_check CHECK (billing_cycle IS NULL OR billing_cycle IN ('monthly', 'quarterly', 'yearly'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_subscriptions DROP CONSTRAINT IF EXISTS tenant_subscriptions_cycle_check');
        }

        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'billing_cycle']);
            $table->dropColumn('billing_cycle');
        });
    }
};
