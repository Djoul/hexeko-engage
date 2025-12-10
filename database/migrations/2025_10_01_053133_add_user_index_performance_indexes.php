<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Composite index for common filter + default sort
        // Optimizes: WHERE enabled = X AND deleted_at IS NULL ORDER BY created_at DESC
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_users_enabled_created_at
            ON users (enabled, created_at DESC)
            WHERE deleted_at IS NULL
        ');

        // Full-text search indexes using pg_trgm for ILIKE optimization
        // Optimizes: WHERE email ILIKE '%search%' OR first_name ILIKE '%search%' OR last_name ILIKE '%search%'
        // Note: Requires pg_trgm extension (usually already enabled)
        DB::statement('
            CREATE EXTENSION IF NOT EXISTS pg_trgm
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_users_email_trgm
            ON users USING gin (email gin_trgm_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_users_first_name_trgm
            ON users USING gin (first_name gin_trgm_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_users_last_name_trgm
            ON users USING gin (last_name gin_trgm_ops)
        ');

        // Financer pivot table index for status filtering
        // Optimizes: WHERE financer_id = X AND active = true
        // Note: Status in this system = financer_user.active field (true=active, false=inactive)
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_financer_user_active
            ON financer_user (financer_id, active)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_users_enabled_created_at');
        DB::statement('DROP INDEX IF EXISTS idx_users_email_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_first_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_users_last_name_trgm');
        DB::statement('DROP INDEX IF EXISTS idx_financer_user_active');
    }
};
