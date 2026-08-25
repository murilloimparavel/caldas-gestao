<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase as BaseRefreshDatabase;

trait RefreshDatabase
{
    use BaseRefreshDatabase;

    /**
     * Keep schema changes on the privileged migration connection while each
     * test transaction continues to use the runtime connection.
     *
     * @return array<string, bool|string>
     */
    protected function migrateFreshUsing(): array
    {
        $seeder = $this->seeder();

        return array_merge(
            [
                '--database' => $this->databaseConnectionForSchemaChanges(),
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ],
            $seeder ? ['--seeder' => $seeder] : ['--seed' => $this->shouldSeed()],
        );
    }

    private function databaseConnectionForSchemaChanges(): string
    {
        $runtimeConnection = config('database.default');

        if ($runtimeConnection !== 'pgsql') {
            return is_string($runtimeConnection) ? $runtimeConnection : 'sqlite';
        }

        return config('database.connections.migration.driver') === 'pgsql'
            ? 'migration'
            : 'pgsql';
    }
}
