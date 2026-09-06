<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('subscription_cycle_usages', function (Blueprint $table): void {
            $table->foreign(
                ['tenant_id', 'customer_subscription_id', 'subscription_cycle_id'],
                'cycle_usages_cycle_subscription_fk',
            )->references(['tenant_id', 'customer_subscription_id', 'id'])->on('subscription_cycles')->cascadeOnDelete();
        });

        Schema::table('subscription_usage_entries', function (Blueprint $table): void {
            $table->foreign(
                ['tenant_id', 'customer_subscription_id', 'subscription_cycle_id'],
                'usage_entries_cycle_subscription_fk',
            )->references(['tenant_id', 'customer_subscription_id', 'id'])->on('subscription_cycles')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'customer_subscription_id', 'subscription_cycle_usage_id'],
                'usage_entries_cycle_usage_subscription_fk',
            )->references(['tenant_id', 'customer_subscription_id', 'id'])->on('subscription_cycle_usages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_usage_entries DROP CONSTRAINT IF EXISTS usage_entries_cycle_subscription_fk');
            DB::statement('ALTER TABLE subscription_usage_entries DROP CONSTRAINT IF EXISTS usage_entries_cycle_usage_subscription_fk');
            DB::statement('ALTER TABLE subscription_cycle_usages DROP CONSTRAINT IF EXISTS cycle_usages_cycle_subscription_fk');

            return;
        }

        Schema::table('subscription_usage_entries', function (Blueprint $table): void {
            $table->dropForeign('usage_entries_cycle_subscription_fk');
            $table->dropForeign('usage_entries_cycle_usage_subscription_fk');
        });

        Schema::table('subscription_cycle_usages', function (Blueprint $table): void {
            $table->dropForeign('cycle_usages_cycle_subscription_fk');
        });
    }
};
