<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RefreshParallelTestSchemas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:refresh-schemas {--force : Force refresh without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all parallel test schemas with current migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will refresh all test schemas. Continue?')) {
            return Command::FAILURE;
        }

        $this->info('Refreshing parallel test schemas...');

        // Set up testing database configuration
        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.database', 'db_engage_testing');
        Config::set('database.connections.pgsql.host', 'db_engage_testing');
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // First, ensure the public schema has all migrations
        $this->info('Ensuring public schema is up to date...');
        DB::statement('SET search_path TO public');
        Artisan::call('migrate', [
            '--database' => 'pgsql',
            '--force' => true,
        ]);

        // Get the number of parallel test workers
        $workerCount = (int) env('PARALLEL_TEST_WORKERS', 12);

        for ($i = 1; $i <= $workerCount; $i++) {
            $schema = "test_{$i}";
            $this->info("Processing schema: {$schema}");

            try {
                // Drop and recreate schema
                DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
                DB::statement("CREATE SCHEMA {$schema}");

                // Copy all table structures from public schema to test schema
                $tables = DB::select("
                    SELECT tablename
                    FROM pg_tables
                    WHERE schemaname = 'public'
                    ORDER BY tablename
                ");

                foreach ($tables as $table) {
                    // Create table structure by copying from public schema
                    DB::statement("
                        CREATE TABLE {$schema}.{$table->tablename}
                        (LIKE public.{$table->tablename} INCLUDING ALL)
                    ");
                }

                $this->info("✓ Schema {$schema} refreshed successfully");
            } catch (Exception $e) {
                $this->error("✗ Failed to refresh schema {$schema}: ".$e->getMessage());
            }
        }

        // Reset search path to public
        DB::statement('SET search_path TO public');

        $this->info('All test schemas have been refreshed!');

        return Command::SUCCESS;
    }
}
