<?php

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Seeder;

class IntegrationResourcesCountSeeder extends Seeder
{
    /**
     * Run the database seeds to update integrations with resources_count_query
     */
    public function run(): void
    {
        // Internal Link query
        Integration::where('name', 'Internal Link')
            ->update([
                'resources_count_query' => 'SELECT COUNT(*) as count FROM int_outils_rh_links WHERE financer_id = :financer_id AND deleted_at IS NULL',
            ]);

        // Communication RH (Internal Communication) query
        Integration::where('name', 'Communication RH')
            ->update([
                'resources_count_query' => "
                    SELECT COUNT(DISTINCT a.id) as count 
                    FROM int_communication_rh_articles a
                    INNER JOIN int_communication_rh_article_translations at ON a.id = at.article_id
                    WHERE a.financer_id = :financer_id 
                    AND at.status = 'published'
                    AND at.language = :language
                    AND a.deleted_at IS NULL
                ",
            ]);

        // Amilon query - counts merchants (no soft deletes on this table)
        Integration::where('name', 'Amilon')
            ->update([
                'resources_count_query' => 'SELECT COUNT(*) as count FROM int_vouchers_amilon_merchants',
            ]);

        // Wellwo - hardcoded value as specified
        Integration::where('name', 'Wellwo')
            ->update([
                'resources_count_query' => 'SELECT 20 as count',
            ]);
    }
}
