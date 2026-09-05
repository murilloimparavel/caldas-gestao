<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        // These keys make every composite relationship below prove the full
        // tenant/unit/subscription scope instead of relying on an id alone.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS subscription_cycles_scope_id_unique ON subscription_cycles (tenant_id, unit_id, customer_subscription_id, id)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS subscription_cycle_usages_scope_id_unique ON subscription_cycle_usages (tenant_id, unit_id, customer_subscription_id, id)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS subscription_cycle_usages_service_scope_unique ON subscription_cycle_usages (tenant_id, unit_id, customer_subscription_id, id, subscription_cycle_id, service_id)');
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS subscription_cycles_one_open_scope_unique ON subscription_cycles (tenant_id, unit_id, customer_subscription_id) WHERE status = 'open'");

        // SQLite cannot alter existing foreign keys portably. Its migrations
        // still receive the unique indexes used by the feature tests.
        if ($driver === 'sqlite') {
            return;
        }

        $constraints = [
            'subscription_cycle_usages' => [
                'cycle_usages_cycle_subscription_fk',
                'subscription_cycle_usages_tenant_id_subscription_cycle_id_foreign',
            ],
            'subscription_usage_entries' => [
                'usage_entries_cycle_subscription_fk',
                'usage_entries_cycle_usage_subscription_fk',
                'subscription_usage_entries_tenant_id_subscription_cycle_id_foreign',
                'subscription_usage_entries_tenant_id_subscription_cycle_usage_id_foreign',
            ],
            'subscription_renewal_attempts' => [
                'subscription_renewal_attempts_tenant_id_source_cycle_id_foreign',
                'subscription_renewal_attempts_tenant_id_target_cycle_id_foreign',
            ],
        ];

        foreach ($constraints as $table => $names) {
            foreach ($names as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }

        DB::statement('ALTER TABLE subscription_cycle_usages ADD CONSTRAINT cycle_usages_cycle_scope_fk FOREIGN KEY (tenant_id, unit_id, customer_subscription_id, subscription_cycle_id) REFERENCES subscription_cycles (tenant_id, unit_id, customer_subscription_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE subscription_usage_entries ADD CONSTRAINT usage_entries_cycle_scope_fk FOREIGN KEY (tenant_id, unit_id, customer_subscription_id, subscription_cycle_id) REFERENCES subscription_cycles (tenant_id, unit_id, customer_subscription_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE subscription_usage_entries ADD CONSTRAINT usage_entries_cycle_usage_service_scope_fk FOREIGN KEY (tenant_id, unit_id, customer_subscription_id, subscription_cycle_usage_id, subscription_cycle_id, service_id) REFERENCES subscription_cycle_usages (tenant_id, unit_id, customer_subscription_id, id, subscription_cycle_id, service_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE subscription_renewal_attempts ADD CONSTRAINT renewal_attempts_source_cycle_scope_fk FOREIGN KEY (tenant_id, unit_id, customer_subscription_id, source_cycle_id) REFERENCES subscription_cycles (tenant_id, unit_id, customer_subscription_id, id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE subscription_renewal_attempts ADD CONSTRAINT renewal_attempts_target_cycle_scope_fk FOREIGN KEY (tenant_id, unit_id, customer_subscription_id, target_cycle_id) REFERENCES subscription_cycles (tenant_id, unit_id, customer_subscription_id, id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE subscription_renewal_attempts DROP CONSTRAINT IF EXISTS renewal_attempts_source_cycle_scope_fk');
            DB::statement('ALTER TABLE subscription_renewal_attempts DROP CONSTRAINT IF EXISTS renewal_attempts_target_cycle_scope_fk');
            DB::statement('ALTER TABLE subscription_usage_entries DROP CONSTRAINT IF EXISTS usage_entries_cycle_scope_fk');
            DB::statement('ALTER TABLE subscription_usage_entries DROP CONSTRAINT IF EXISTS usage_entries_cycle_usage_service_scope_fk');
            DB::statement('ALTER TABLE subscription_cycle_usages DROP CONSTRAINT IF EXISTS cycle_usages_cycle_scope_fk');
        }

        DB::statement('DROP INDEX IF EXISTS subscription_cycles_one_open_scope_unique');
        DB::statement('DROP INDEX IF EXISTS subscription_cycle_usages_service_scope_unique');
        DB::statement('DROP INDEX IF EXISTS subscription_cycle_usages_scope_id_unique');
        DB::statement('DROP INDEX IF EXISTS subscription_cycles_scope_id_unique');
    }
};
