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
        // Check if the old unique constraint on 'key' exists
        $existingConstraints = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'translation_keys'::regclass
            AND contype = 'u'
            AND conname = 'translation_keys_key_unique'
        ");

        if (count($existingConstraints) > 0) {
            Schema::table('translation_keys', function (Blueprint $table): void {
                // Drop the old unique constraint on 'key' only
                $table->dropUnique('translation_keys_key_unique');
            });
        }

        // Ensure we don't have duplicate entries that would violate the new constraint
        // First, get duplicates using proper PostgreSQL syntax
        $duplicates = DB::select("
            SELECT \"key\", \"group\", interface_origin, string_agg(id::text, ',') as ids, count(*) as cnt
            FROM translation_keys
            GROUP BY \"key\", \"group\", interface_origin
            HAVING count(*) > 1
        ");

        // Keep only the first ID for each duplicate set
        foreach ($duplicates as $duplicate) {
            $ids = explode(',', $duplicate->ids);
            array_shift($ids); // Remove first ID (we'll keep this one)

            if ($ids !== []) {
                DB::table('translation_keys')->whereIn('id', $ids)->delete();
            }
        }

        // Check if the new constraint already exists
        $newConstraintExists = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'translation_keys'::regclass
            AND contype = 'u'
            AND conname = 'translation_keys_unique'
        ");

        // Only add if it doesn't exist
        if (count($newConstraintExists) === 0) {
            Schema::table('translation_keys', function (Blueprint $table): void {
                // Ensure unique constraint is on key, group, and interface_origin
                $table->unique(['key', 'group', 'interface_origin'], 'translation_keys_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the new constraint exists before dropping
        $newConstraintExists = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'translation_keys'::regclass
            AND contype = 'u'
            AND conname = 'translation_keys_unique'
        ");

        if (count($newConstraintExists) > 0) {
            Schema::table('translation_keys', function (Blueprint $table): void {
                $table->dropUnique('translation_keys_unique');
            });
        }

        // Restore the old unique constraint on key only
        Schema::table('translation_keys', function (Blueprint $table): void {
            $table->unique('key', 'translation_keys_key_unique');
        });
    }
};
