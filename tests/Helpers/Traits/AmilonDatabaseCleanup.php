<?php

namespace Tests\Helpers\Traits;

use Illuminate\Support\Facades\DB;

trait AmilonDatabaseCleanup
{
    /**
     * Clean up all Amilon-related database tables in the correct order
     * to avoid foreign key constraint violations.
     */
    protected function cleanupAmilonDatabase(): void
    {
        // Clean up in the correct order to avoid foreign key constraints
        // Check if tables exist before trying to delete from them
        $tables = [
            'int_vouchers_amilon_order_items',
            'int_vouchers_amilon_orders',
            'int_vouchers_amilon_products',
            'int_vouchers_amilon_merchant_category',
            'int_vouchers_amilon_merchants',
            'int_vouchers_amilon_categories',
            'int_vouchers_amilon_processed_events',
        ];

        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    /**
     * Clean up users table (be careful, this affects all users!)
     */
    protected function cleanupUsers(): void
    {
        DB::table('users')->delete();
    }
}
