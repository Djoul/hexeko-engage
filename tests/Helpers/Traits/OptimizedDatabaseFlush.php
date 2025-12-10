<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

trait OptimizedDatabaseFlush
{
    /**
     * Deterministic child→parent delete order for known families.
     * Only tables present in current schema will be considered.
     */
    protected static array $priorityDeleteOrder = [
        // Spatie (children first)
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',

        // Vouchers/Amilon
        'int_vouchers_amilon_order_items',
        'int_vouchers_amilon_merchant_category',
        'int_vouchers_amilon_products',
        'int_vouchers_amilon_orders',
        'int_vouchers_amilon_merchants',
        'int_vouchers_amilon_categories',

        // Internal Communication
        'int_communication_rh_article_interactions',
        'int_communication_rh_article_tag',
        'int_communication_rh_article_versions',
        'int_communication_rh_article_translations',
        'int_communication_rh_articles',
        'int_communication_rh_tags',

        // HR Tools
        'int_outils_rh_link_user',
        'int_outils_rh_links',

        // Core pivots and logs
        'financer_user',
        'invited_users',
        'user_pinned_modules',
        'translation_values',
        'translation_activity_logs',
        'int_stripe_payments',
        'llm_requests',
        'audits',
        'activity_log',

        // Domain parents (safe near end)
        'translation_keys',
        'financers',
        'divisions',
        'teams',
        'users',
        'permissions',
        'roles',
        'modules',
    ];

    /**
     * Master→children mapping to expand requested subsets to include dependents.
     * Order children from most dependent to less when possible.
     *
     * @var array<string, array<int,string>>
     */
    protected static array $dependentsMap = [
        'financers' => [
            'financer_user',
            'invited_users',
            'llm_requests',
            'int_communication_rh_articles',
            'int_communication_rh_tags',
            'int_outils_rh_links',
            'financer_module',
        ],
        'int_communication_rh_articles' => [
            'int_communication_rh_article_interactions',
            'int_communication_rh_article_tag',
            'int_communication_rh_article_versions',
            'int_communication_rh_article_translations',
        ],
        'int_outils_rh_links' => [
            'int_outils_rh_link_user',
        ],
        'divisions' => [
            'financers',
        ],
        'users' => [
            'financer_user',
            'user_pinned_modules',
            'int_stripe_payments',
            'int_communication_rh_articles',
        ],
        'roles' => [
            'model_has_roles',
            'role_has_permissions',
        ],
        'permissions' => [
            'model_has_permissions',
            'role_has_permissions',
        ],
        'int_communication_rh_tags' => [
            'int_communication_rh_article_tag',
        ],
    ];

    /**
     * Flush all database tables in a transaction using DELETE-only operations.
     * - Uses advisory transaction lock to avoid rare cross-process conflicts
     * - Defers deferrable constraints for PostgreSQL
     * - Deletes known children first, then remaining tables
     */
    protected function flushDatabaseTables(?array $onlyTables = null, bool $expand = true): void
    {
        $driver = config('database.default');

        if ($driver !== 'pgsql') {
            throw new RuntimeException('OptimizedDatabaseFlush supports only the pgsql driver.');
        }

        DB::beginTransaction();
        try {
            // Safety: transaction-scoped advisory lock, scoped per schema to avoid cross-worker blocking
            $schemaRow = DB::select('select current_schema() as schema');
            $schema = $schemaRow[0]->schema ?? 'public';
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?)) as ok', ['tests_flush_'.$schema]);

            // Defer constraints (deferrable ones) for duration of this transaction
            DB::statement('SET CONSTRAINTS ALL DEFERRED');

            $all = $this->getExistingTablesInCurrentSchema();
            $targets = $this->filterTargetTables($all, $onlyTables, $expand);
            $existingLookup = array_fill_keys($targets, true);

            // 1) Delete in priority order (children → parents)
            foreach (static::$priorityDeleteOrder as $table) {
                if (isset($existingLookup[$table]) && $table !== 'migrations') {
                    try {
                        // Use savepoint to isolate potential errors in PostgreSQL
                        DB::statement('SAVEPOINT delete_table');
                        DB::table($table)->delete();
                        DB::statement('RELEASE SAVEPOINT delete_table');
                        unset($existingLookup[$table]);
                    } catch (QueryException $e) {
                        // Rollback to savepoint to recover transaction state
                        DB::statement('ROLLBACK TO SAVEPOINT delete_table');

                        $sqlState = (string) ($e->getCode() ?? '');
                        // Defer to remaining phase on FK issues
                        if ($sqlState === '23503') {
                            // leave in existingLookup so it will be processed later
                            continue;
                        }
                        if (str_contains($e->getMessage(), 'foreign key')) {
                            // leave in existingLookup so it will be processed later
                            continue;
                        }
                        throw $e;
                    }
                }
            }

            // 2) Delete remaining tables in a resilient order
            $remaining = array_values(array_filter(array_keys($existingLookup), fn (int|string $t): bool => $t !== 'migrations'));

            // Simple resilient loop: attempt deletes; on FK violation, move table to end and retry
            $maxPasses = count($remaining) > 0 ? (count($remaining) * 2) : 0;
            $i = 0;
            while ($remaining !== [] && $i < $maxPasses) {
                $table = array_shift($remaining);
                try {
                    // Use savepoint to isolate potential errors in PostgreSQL
                    DB::statement('SAVEPOINT delete_remaining_table');
                    DB::table($table)->delete();
                    DB::statement('RELEASE SAVEPOINT delete_remaining_table');
                } catch (QueryException $e) {
                    // Rollback to savepoint to recover transaction state
                    DB::statement('ROLLBACK TO SAVEPOINT delete_remaining_table');

                    $sqlState = (string) ($e->getCode() ?? '');
                    // 23503 = foreign_key_violation
                    if ($sqlState === '23503' || str_contains($e->getMessage(), 'foreign key')) {
                        // Try later after other children
                        $remaining[] = $table;
                    } else {
                        throw $e;
                    }
                }
                $i++;
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Filter target tables when a subset is requested.
     *
     * @param  array<int,string>  $allTables
     * @param  array<int,string>|null  $onlyTables
     * @return array<int,string>
     */
    private function filterTargetTables(array $allTables, ?array $onlyTables, bool $expand): array
    {
        if ($onlyTables === null || $onlyTables === []) {
            return $allTables;
        }
        $expanded = $expand ? $this->expandWithDependents($onlyTables) : $onlyTables;
        $onlyLookup = array_fill_keys($expanded, true);
        $filtered = [];
        foreach ($allTables as $t) {
            if (isset($onlyLookup[$t])) {
                $filtered[] = $t;
            }
        }

        return $filtered;
    }

    /**
     * Expand requested tables with their dependents recursively and order by priority list.
     *
     * @param  array<int,string>  $requested
     * @return array<int,string>
     */
    private function expandWithDependents(array $requested): array
    {
        $queue = array_values(array_unique($requested));
        $seen = [];
        $result = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $result[] = $current;
            // Static mapping
            foreach (static::$dependentsMap[$current] ?? [] as $child) {
                if (! isset($seen[$child])) {
                    $queue[] = $child;
                }
            }
            // Dynamic discovery via information_schema
            foreach ($this->getFkChildren($current) as $child) {
                if (! isset($seen[$child])) {
                    $queue[] = $child;
                }
            }
        }

        // Order by priority list (children earlier)
        $priorityIndex = array_flip(static::$priorityDeleteOrder);
        usort($result, static function (string $a, string $b) use ($priorityIndex): int {
            $pa = $priorityIndex[$a] ?? PHP_INT_MAX;
            $pb = $priorityIndex[$b] ?? PHP_INT_MAX;

            return $pa <=> $pb;
        });

        return $result;
    }

    /**
     * Cached map of FK children per parent table in current schema.
     *
     * @var array<string, array<int,string>>|null
     */
    private static ?array $fkChildCache = null;

    /**
     * Discover FK children for a given parent table in the current schema, using a cached mapping.
     *
     * @return array<int,string>
     */
    private function getFkChildren(string $parentTable): array
    {
        if (self::$fkChildCache === null) {
            $rows = DB::select(
                "SELECT tc.table_name AS child_table, ccu.table_name AS parent_table\n                 FROM information_schema.table_constraints AS tc\n                 JOIN information_schema.key_column_usage AS kcu\n                   ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema\n                 JOIN information_schema.referential_constraints AS rc\n                   ON tc.constraint_name = rc.constraint_name AND tc.table_schema = rc.constraint_schema\n                 JOIN information_schema.constraint_column_usage AS ccu\n                   ON rc.unique_constraint_name = ccu.constraint_name AND rc.unique_constraint_schema = ccu.constraint_schema\n                WHERE tc.constraint_type = 'FOREIGN KEY'\n                  AND ccu.table_schema = current_schema()"
            );
            $map = [];
            foreach ($rows as $r) {
                $parent = (string) $r->parent_table;
                $child = (string) $r->child_table;
                if ($child === $parent) {
                    continue;
                }
                $map[$parent] ??= [];
                $map[$parent][] = $child;
            }
            self::$fkChildCache = $map;
        }

        return array_values(array_unique(self::$fkChildCache[$parentTable] ?? []));
    }

    /**
     * Get list of existing base tables in the current schema (first schema in search_path).
     * Excludes system schemas automatically.
     *
     * @return array<int, string>
     */
    protected function getExistingTablesInCurrentSchema(): array
    {
        $driver = config('database.default');

        if ($driver !== 'pgsql') {
            throw new RuntimeException('OptimizedDatabaseFlush supports only the pgsql driver.');
        }

        $schemaRow = DB::select('select current_schema() as schema');
        $schema = $schemaRow[0]->schema ?? 'public';

        $rows = DB::select(
            "select table_name from information_schema.tables where table_schema = ? and table_type = 'BASE TABLE'",
            [$schema]
        );

        return array_map(static fn ($r): string => (string) $r->table_name, $rows);
    }

    /**
     * Determine if tables should be flushed. Override in a test to enable.
     */
    protected function shouldFlushTables(): bool
    {
        return false;
    }
}
