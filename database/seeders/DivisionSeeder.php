<?php

namespace Database\Seeders;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\Languages;
use App\Models\Division;
use Illuminate\Support\Facades\App;

class DivisionSeeder extends BaseSeeder
{
    public function run(): void
    {
        $divisions = [
            [
                'id' => '019904a4-099c-731f-8a18-a65dd25fd7f9',
                'name' => 'Belgique',
                'remarks' => 'Filiale de Belgique',
                'country' => Countries::BELGIUM,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Brussels',
                'language' => Languages::FRENCH,
            ],
            [
                'id' => '019904a4-099d-73bd-8c12-35b21bf77cb6',
                'name' => 'Portugal',
                'remarks' => 'Filiale de Portugal',
                'country' => Countries::PORTUGAL,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Lisbon',
                'language' => Languages::PORTUGUESE,
            ],
        ];
        $devDivisions = [
            [
                'id' => '019904a4-099e-73cf-a8ce-a64bf44d0522',
                'name' => 'France',
                'remarks' => 'Filiale de France',
                'country' => Countries::FRANCE,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Paris',
                'language' => Languages::FRENCH,
                'demo' => true,
            ],
            [
                'id' => '019904e0-6dee-7042-a340-235add484103',
                'name' => 'United Kingdom',
                'remarks' => 'Filiale du Royaume-Uni',
                'country' => Countries::UNITED_KINGDOM,
                'currency' => Currencies::GBP,
                'timezone' => 'Europe/London',
                'language' => Languages::ENGLISH,
                'demo' => true,
            ],
            [
                'id' => '019904a4-09a1-72f3-a42d-4cf5afbdee9a',
                'name' => 'Roumanie',
                'remarks' => 'Filiale de Roumanie',
                'country' => Countries::ROMANIA,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Bucharest',
                'language' => Languages::ROMANIAN,
                'demo' => true,
            ],
        ];

        if (in_array(App::environment(), ['local', 'dev', 'staging'])) {
            $divisions = array_merge($divisions, $devDivisions);
        }

        foreach ($divisions as $divisionArray) {
            $isDemo = $divisionArray['demo'] ?? false;
            unset($divisionArray['demo']);
            $division = Division::create($divisionArray);
            if ($isDemo) {
                $division->markAsDemo();
            }
        }
    }
}
