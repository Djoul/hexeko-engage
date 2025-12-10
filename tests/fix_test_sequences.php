<?php

/**
 * Fix PostgreSQL sequences in test schemas for parallel testing
 * This script ensures all auto-increment sequences are properly configured
 */
function fixTestSequences(PDO $pdo, string $schema): void
{
    try {
        // Get all tables with ID columns that should have sequences
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                c.table_name,
                pg_get_serial_sequence('public.' || c.table_name, 'id') as public_seq
            FROM information_schema.columns c
            JOIN information_schema.tables t
                ON c.table_schema = t.table_schema
                AND c.table_name = t.table_name
            WHERE c.table_schema = :schema
                AND c.column_name = 'id'
                AND c.data_type IN ('bigint', 'integer')
                AND t.table_type = 'BASE TABLE'
        ");
        $stmt->execute(['schema' => $schema]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tableName = $row['table_name'];

            // Determine the sequence name
            if ($row['public_seq']) {
                // Use the same sequence name as the public schema
                $sequenceName = str_replace('public.', '', $row['public_seq']);
            } else {
                // Default sequence naming
                $sequenceName = $tableName.'_id_seq';
            }

            try {
                // Create sequence if it doesn't exist
                $pdo->exec("CREATE SEQUENCE IF NOT EXISTS \"{$schema}\".\"{$sequenceName}\"");

                // Set column default to use the sequence
                $pdo->exec("
                    ALTER TABLE \"{$schema}\".\"{$tableName}\"
                    ALTER COLUMN id SET DEFAULT nextval('\"{$schema}\".\"{$sequenceName}\"'::regclass)
                ");

                // Reset sequence to max(id) + 1
                $pdo->exec("
                    SELECT setval('\"{$schema}\".\"{$sequenceName}\"',
                        COALESCE((SELECT MAX(id) FROM \"{$schema}\".\"{$tableName}\"), 0) + 1,
                        false)
                ");

            } catch (PDOException $e) {
                // Silently skip errors (sequence might already exist, etc.)
            }
        }
    } catch (Throwable $e) {
        // Don't fail the bootstrap
        fwrite(STDERR, "[fix_test_sequences] Failed to fix sequences in schema {$schema}: ".$e->getMessage()."\n");
    }
}
