<?php

namespace Tests;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Permission;
use Artisan;
use Database\Seeders\TestingSeeder;
use DB;
use Exception;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionClass;
use Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\Attributes\FlushTables as FlushTablesAttribute;
use Tests\Helpers\Attributes\Skip as SkipAttribute;
use Tests\Helpers\Traits\OptimizedDatabaseFlush;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use OptimizedDatabaseFlush;

    protected bool $bootDatabase = true;

    /**
     * Track classes that already ran class-scoped flush in this PHP process.
     *
     * @var array<string,bool>
     */
    private static array $classFlushDone = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Check if the test class has the Skip attribute
        $this->checkSkipAttribute();

        // We do our tests only within the testing environment.
        if (app()->environment() !== 'testing') {
            abort(401, 'Tests should only be performed within the testing environment!');
        }

        if ($this->bootDatabase) {
            // Only run migrations if tables don't exist yet
            // This prevents "table already exists" errors when test schemas are pre-populated
            // Check if a key table exists (migrations table should always exist)
            if (! Schema::hasTable('migrations') || ! Schema::hasTable('teams')) {
                Artisan::call('migrate');
            } else {
                // Tables exist, check if there are pending migrations by checking the count
                try {
                    // Get count of migration files
                    $migrationFiles = count(glob(database_path('migrations/*.php'))) +

                    // Get count of executed migrations
                    $executedMigrations = DB::table('migrations')->count();

                    // Only run if there are pending migrations
                    if ($migrationFiles > $executedMigrations) {
                        // Run migrations but suppress errors for already existing tables
                        Artisan::call('migrate', ['--force' => true]);
                    }
                } catch (Exception $e) {
                    // Silently skip if there's any issue checking migrations
                    // The tests will fail if tables are actually missing
                }
            }

            // Seed once per worker schema (idempotent)
            $this->ensureTestingSeededOnce();

            // Optional deterministic DELETE-only flush via attribute on the class
            $cfg = $this->resolveFlushConfigFromAttribute();
            if ($cfg !== null && ($cfg['enabled'] ?? false)) {
                $tables = $cfg['tables'] ?? [];
                $expand = (bool) ($cfg['expand'] ?? true);
                $scope = (string) ($cfg['scope'] ?? 'test');

                if ($scope === 'class') {
                    $key = static::class;
                    if (! isset(self::$classFlushDone[$key])) {
                        if (! empty($tables)) {
                            $this->flushDatabaseTables($tables, $expand);
                        } else {
                            $this->flushDatabaseTables();
                        }
                        self::$classFlushDone[$key] = true;
                    }
                } elseif (! empty($tables)) {
                    // default: test scope
                    $this->flushDatabaseTables($tables, $expand);
                } else {
                    $this->flushDatabaseTables();
                }
            }
        }
    }

    /**
     * Check if the test class has the Skip attribute and skip all tests if found.
     */
    protected function checkSkipAttribute(): void
    {
        $ref = new ReflectionClass(static::class);
        $attrs = $ref->getAttributes(SkipAttribute::class);

        if ($attrs !== []) {
            /** @var SkipAttribute $inst */
            $inst = $attrs[0]->newInstance();
            $this->markTestSkipped($inst->reason);
        }
    }

    private function ensureTestingSeededOnce(): void
    {
        try {
            // If permissions table missing, migrations likely incomplete for this schema
            if (! Schema::hasTable('permissions')) {
                return;
            }

            $expected = \count(PermissionDefaults::asArray());
            $current = (int) Permission::count();
            if ($current < $expected) {
                // Seed minimal testing data (permissions only)
                $this->artisan('db:seed', ['--class' => TestingSeeder::class, '--no-interaction' => true]);
            }

            // Always make sure Spatie cache is fresh per test boot
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable $e) {
            // Do not fail tests during boot; individual tests will surface issues
            // This keeps Phase 4 non-intrusive.
        }
    }

    /**
     * Resolve requested flush tables from class attribute, if any.
     * Returns:
     * - array of table names to flush when attribute is present and enabled with a list
     * - empty array when attribute present and enabled with no list (means full flush)
     * - null when no attribute or disabled
     */
    protected function resolveFlushConfigFromAttribute(): ?array
    {
        try {
            $ref = new ReflectionClass(static::class);
            $attrs = $ref->getAttributes(FlushTablesAttribute::class);
            if ($attrs !== []) {
                /** @var FlushTablesAttribute $inst */
                $inst = $attrs[0]->newInstance();

                return [
                    'enabled' => (bool) $inst->enabled,
                    'tables' => is_array($inst->tables) ? $inst->tables : [],
                    'scope' => in_array($inst->scope, ['class', 'test'], true) ? $inst->scope : 'test',
                    'expand' => (bool) $inst->expand,
                ];
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }
}
