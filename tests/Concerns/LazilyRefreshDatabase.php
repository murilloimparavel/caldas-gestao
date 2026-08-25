<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase as BaseLazilyRefreshDatabase;

trait LazilyRefreshDatabase
{
    use BaseLazilyRefreshDatabase;

    /**
     * Keep deferred schema changes on the privileged migration connection.
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
