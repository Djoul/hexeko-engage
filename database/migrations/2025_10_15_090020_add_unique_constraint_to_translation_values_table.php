<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds a unique constraint on (translation_key_id, locale) to prevent duplicate translations
     * This is critical for UPSERT operations in the ImportTranslationsAction
     */
    public function up(): void
    {
        // First, ensure we don't have duplicate entries that would violate the new constraint
        $duplicates = DB::select("
            SELECT translation_key_id, locale, string_agg(id::text, ',') as ids, count(*) as cnt
            FROM translation_values
            GROUP BY translation_key_id, locale
            HAVING count(*) > 1
        ");

        // Keep only the first ID for each duplicate set (most recent value)
        foreach ($duplicates as $duplicate) {
            if (! isset($duplicate->ids)) {
                continue;
            }
            if (! is_string($duplicate->ids)) {
                continue;
            }
            $ids = explode(',', $duplicate->ids);
            array_shift($ids); // Remove first ID (we'll keep this one)

            if ($ids !== []) {
                DB::table('translation_values')->whereIn('id', $ids)->delete();
            }
        }

        // Check if the constraint already exists
        $constraintExists = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'translation_values'::regclass
            AND contype = 'u'
            AND conname = 'translation_values_unique'
        ");

        // Only add if it doesn't exist
        if (count($constraintExists) === 0) {
            Schema::table('translation_values', function (Blueprint $table): void {
                // Add unique constraint on translation_key_id and locale
                $table->unique(['translation_key_id', 'locale'], 'translation_values_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the constraint exists before dropping
        $constraintExists = DB::select("
            SELECT conname
            FROM pg_constraint
            WHERE conrelid = 'translation_values'::regclass
            AND contype = 'u'
            AND conname = 'translation_values_unique'
        ");

        if (count($constraintExists) > 0) {
            Schema::table('translation_values', function (Blueprint $table): void {
                $table->dropUnique('translation_values_unique');
            });
        }
    }
};
