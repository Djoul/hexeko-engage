<?php

namespace App\Testing;

use Tests\TestCase as BaseTestCase;

abstract class DatabaseTestCase extends BaseTestCase
{
    /** @var array<string>|null */
    public $connectionsToTransact;

    protected static bool $migrated = false;

    final protected function setUp(): void
    {
        parent::setUp();

        if (! static::$migrated) {
            $this->artisan('migrate:fresh', [
                '--database' => 'pgsql',
                '--env' => 'testing',
                '--force' => true,
            ]);
            static::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    final protected function tearDown(): void
    {
        $this->rollbackDatabaseTransaction();
        parent::tearDown();
    }

    private function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $connection->beginTransaction();
        }
    }

    private function rollbackDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        foreach ($this->connectionsToTransact() as $name) {
            $connection = $database->connection($name);
            $connection->rollBack();
        }
    }

    /**
     * @return array<string>
     */
    final protected function connectionsToTransact(): array
    {
        return property_exists($this, 'connectionsToTransact')
            ? $this->connectionsToTransact
            : ['pgsql'];
    }
}
