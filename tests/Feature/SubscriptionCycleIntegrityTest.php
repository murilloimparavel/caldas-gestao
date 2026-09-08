<?php

use Illuminate\Support\Facades\DB;

it('installs the one-open-cycle scope index on supported local databases', function () {
    if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
        $this->markTestSkipped('The cycle scope index is defined only for PostgreSQL and SQLite.');
    }

    if (DB::getDriverName() === 'pgsql') {
        $index = DB::selectOne(
            "select indexdef from pg_indexes where schemaname = current_schema() and indexname = 'subscription_cycles_one_open_scope_unique'",
        );

        expect($index?->indexdef)->toContain('(tenant_id, unit_id, customer_subscription_id)')
            ->and($index?->indexdef)->toMatch('/status.*open/');

        return;
    }

    $indexes = DB::select("pragma index_list('subscription_cycles')");

    expect(collect($indexes)->pluck('name')->all())
        ->toContain('subscription_cycles_one_open_scope_unique');
});

it('installs subscription scoped composite constraints on PostgreSQL', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Composite cycle constraints are enforced by the PostgreSQL integration job.');
    }

    $constraints = DB::select(
        <<<'SQL'
        select conname, pg_get_constraintdef(oid) as definition
        from pg_constraint
        where conname in (
            'cycle_usages_cycle_scope_fk',
            'usage_entries_cycle_scope_fk',
            'usage_entries_cycle_usage_service_scope_fk',
            'renewal_attempts_source_cycle_scope_fk',
            'renewal_attempts_target_cycle_scope_fk'
        )
        SQL,
    );

    expect(collect($constraints)->pluck('conname')->all())
        ->toHaveCount(5)
        ->toEqualCanonicalizing([
            'cycle_usages_cycle_scope_fk',
            'usage_entries_cycle_scope_fk',
            'usage_entries_cycle_usage_service_scope_fk',
            'renewal_attempts_source_cycle_scope_fk',
            'renewal_attempts_target_cycle_scope_fk',
        ]);

    expect(collect($constraints)->firstWhere('conname', 'usage_entries_cycle_usage_service_scope_fk')->definition)
        ->toContain('service_id');
});
