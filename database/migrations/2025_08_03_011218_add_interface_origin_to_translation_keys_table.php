<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('translation_keys', function (Blueprint $table): void {
            $table->string('interface_origin', 50)->default('web')->after('group');

            // Add index for performance
            $table->index('interface_origin', 'idx_translation_keys_interface');
        });

        // Handle unique constraint separately to check if it exists
        $existingIndexes = DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'translation_keys'
            AND indexname LIKE '%unique%'
        ");

        $hasOldUnique = collect($existingIndexes)->pluck('indexname')->contains('translation_keys_key_group_unique');

        if ($hasOldUnique) {
            Schema::table('translation_keys', function (Blueprint $table): void {
                $table->dropUnique('translation_keys_key_group_unique');
            });
        }

        // Add new unique constraint including interface_origin
        Schema::table('translation_keys', function (Blueprint $table): void {
            $table->unique(['key', 'group', 'interface_origin'], 'translation_keys_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('translation_keys', function (Blueprint $table): void {
            // Drop new constraints
            $table->dropUnique('translation_keys_unique');
            $table->dropIndex('idx_translation_keys_interface');

            // Restore old unique constraint
            $table->unique(['key', 'group'], 'translation_keys_key_group_unique');

            // Drop column
            $table->dropColumn('interface_origin');
        });
    }
};
