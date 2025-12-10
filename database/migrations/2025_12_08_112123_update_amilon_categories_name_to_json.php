<?php

use App\Settings\General\LocalizationSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $localizationSettings = app(LocalizationSettings::class);
        $availableLocales = $localizationSettings->available_locales;

        // Store existing data
        $categories = DB::table('int_vouchers_amilon_categories')->get();

        // Change column type to JSON using raw SQL for PostgreSQL
        DB::statement('ALTER TABLE int_vouchers_amilon_categories ALTER COLUMN name TYPE json USING \'{}\'::json');

        // Migrate existing data to JSON format
        foreach ($categories as $category) {
            $translatedName = [];

            // Set the default name for all locales
            foreach ($availableLocales as $locale) {
                $translatedName[$locale] = $category->name;
            }

            DB::table('int_vouchers_amilon_categories')
                ->where('id', $category->id)
                ->update(['name' => json_encode($translatedName)]);
        }
    }
};
