<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixTestSchemaSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:fix-sequences {--schema=all : Schema to fix (all, test_1, test_2, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix auto-increment sequences in test database schemas for parallel testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schemaOption = $this->option('schema');

        // Connect to test database
        config(['database.default' => 'pgsql']);
        config(['database.connections.pgsql.database' => env('DB_DATABASE', 'db_engage_testing')]);

        // Get all test schemas
        $schemas = $this->getTestSchemas($schemaOption);

        $this->info('Fixing sequences for schemas: '.implode(', ', $schemas));

        foreach ($schemas as $schema) {
            $this->info("Processing schema: $schema");
            $this->fixSchemaSequences($schema);
        }

        $this->info('✅ All sequences fixed successfully!');

        return Command::SUCCESS;
    }

    private function getTestSchemas(string $option): array
    {
        if ($option !== 'all') {
            return [$option];
        }

        $result = DB::select("
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name LIKE 'test_%'
               OR schema_name = 'public'
            ORDER BY schema_name
        ");

        return array_map(fn ($row) => $row->schema_name, $result);
    }

    private function fixSchemaSequences(string $schema): void
    {
        // Get all tables with bigint/integer ID columns in this schema
        $tables = DB::select("
            SELECT
                c.table_name,
                c.column_name,
                c.column_default
            FROM information_schema.columns c
            JOIN information_schema.tables t
                ON c.table_schema = t.table_schema
                AND c.table_name = t.table_name
            WHERE c.table_schema = ?
                AND c.column_name = 'id'
                AND c.data_type IN ('bigint', 'integer')
                AND t.table_type = 'BASE TABLE'
            ORDER BY c.table_name
        ", [$schema]);

        foreach ($tables as $table) {
            $tableName = $table->table_name;
            $sequenceName = "{$tableName}_id_seq";

            $this->line("  - Fixing $tableName...");

            try {
                // Create sequence if it doesn't exist
                DB::statement("
                    DO $$
                    BEGIN
                        IF NOT EXISTS (
                            SELECT 1 FROM pg_sequences
                            WHERE schemaname = '{$schema}'
                            AND sequencename = '{$sequenceName}'
                        ) THEN
                            CREATE SEQUENCE {$schema}.{$sequenceName};
                        END IF;
                    END $$;
                ");

                // Set column default to use the sequence
                DB::statement("
                    ALTER TABLE {$schema}.{$tableName}
                    ALTER COLUMN id SET DEFAULT nextval('{$schema}.{$sequenceName}'::regclass)
                ");

                // Reset sequence to max ID + 1
                DB::statement("
                    SELECT setval('{$schema}.{$sequenceName}',
                        COALESCE((SELECT MAX(id) FROM {$schema}.{$tableName}), 0) + 1,
                        false)
                ");

            } catch (Exception $e) {
                $this->error('    Failed: '.$e->getMessage());
            }
        }
    }
}
