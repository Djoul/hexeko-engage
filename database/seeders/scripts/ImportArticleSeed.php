<?php

namespace Database\Seeders\scripts;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportArticleSeed
{
    public function run(): void
    {
        $sqlFiles = [
            database_path('seeders/sql/final_articles_interactions.sql'),
            database_path('seeders/sql/generated_article_interactions_clean.sql'),
            database_path('seeders/sql/generated_llm_requests.sql.sql'),
        ];

        foreach ($sqlFiles as $sqlFile) {
            if (! File::exists($sqlFile)) {
                echo "Error: SQL file not found at {$sqlFile}\n";

                continue;
            }

            echo "Importing article seed SQL file {$sqlFile}...\n";

            try {
                $sql = File::get($sqlFile);
                DB::unprepared($sql);
                echo "Import completed successfully for {$sqlFile}!\n";
            } catch (Exception $e) {
                echo "Error importing SQL from {$sqlFile}: ".$e->getMessage()."\n";
            }
        }
    }
}
