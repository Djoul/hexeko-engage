<?php

namespace Database\Seeders;

use App\Enums\Integrations\IntegrationTypes;
use App\Models\Financer;
use App\Models\Integration;
use DB;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('integrations')->truncate();
        DB::table('financer_integration')->truncate();

        // Use fixed UUIDs to get modules reliably
        $moduleUuids = [
            'internal_link' => '550e8400-e29b-41d4-a716-446655440001',
            'internal_communication' => '550e8400-e29b-41d4-a716-446655440002',
            'wellness' => '550e8400-e29b-41d4-a716-446655440003',
            'vouchers' => '550e8400-e29b-41d4-a716-446655440004',
            'leisure' => '550e8400-e29b-41d4-a716-446655440005',
            'survey' => '019a7c8a-9e05-737b-93ce-fa3299d62ba7',
        ];

        Integration::create([
            'name' => 'Internal Link',
            'type' => IntegrationTypes::EMBEDDED_SERVICE,
            'module_id' => $moduleUuids['internal_link'],
            'front_endpoint' => 'hr-tools',
            'api_endpoint' => 'HRTools',
            'resources_count_unit' => 'hr_tools_unit',
            'settings' => [
                'namespace' => 'app/integrations/HRTools',
                'db_prefix' => 'int_outils_rh_',
                'route_prefix' => 'outils-rh',
            ],
        ]);

        Integration::create([
            'name' => 'Communication RH',
            'type' => IntegrationTypes::EMBEDDED_SERVICE,
            'module_id' => $moduleUuids['internal_communication'],
            'front_endpoint' => 'internal-communications',
            'api_endpoint' => 'internal-communications',
            'resources_count_unit' => 'internal_communication_unit',
            'settings' => [
                'namespace' => 'app/integrations/InternalCommunication',
                'db_prefix' => 'int_communication_rh',
                'route_prefix' => 'communication-rh',
            ],
        ]);
        Integration::create([
            'name' => 'Amilon',
            'type' => IntegrationTypes::THIRD_PARTY_API,
            'module_id' => $moduleUuids['vouchers'],
            'front_endpoint' => 'voucher/amilon',
            'api_endpoint' => 'voucher/amilon',
            'resources_count_unit' => 'amilon_unit',
            'settings' => [
                'namespace' => 'app/integrations/Voucher/Amilon',
                'db_prefix' => 'int_voucher',
                'route_prefix' => 'vouchers-amilon',
            ],
        ]);
        Integration::create([
            'name' => 'Wellwo',
            'type' => IntegrationTypes::THIRD_PARTY_API,
            'module_id' => $moduleUuids['wellness'],
            'front_endpoint' => 'wellbeing/wellwo',
            'api_endpoint' => 'wellbeing/wellwo',
            'resources_count_unit' => 'wellwo_unit',
            'settings' => [
                'namespace' => 'app/integrations/wellbeing/wellwo',
                'db_prefix' => '',
                'route_prefix' => 'wellbeing-wellwo',
            ],
        ]);
        Integration::create([
            'name' => 'Survey',
            'type' => IntegrationTypes::EMBEDDED_SERVICE,
            'module_id' => $moduleUuids['survey'],
            'front_endpoint' => 'surveys',
            'api_endpoint' => 'survey',
            'resources_count_unit' => 'survey_unit',
            'settings' => [
                'namespace' => 'app/integrations/Survey',
                'db_prefix' => 'int_survey',
                'route_prefix' => 'survey',
            ],
        ]);

        Financer::get()->each(function (Financer $financer): void {
            Integration::get()->each(function (Integration $integration) use ($financer): void {
                if (! $financer->integrations()->wherePivot('integration_id', $integration->id)->exists()) {
                    $financer->integrations()->attach($integration->id, ['active' => true]);
                }
            });
        });
    }
}
