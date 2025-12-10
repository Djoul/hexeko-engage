<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Financer;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancerFactory extends Factory
{
    protected $model = Financer::class;

    public function definition(): array
    {
        $division = Division::inRandomOrder()->first();

        if (! $division instanceof Division) {
            $division = Division::factory()->create();
        }

        $bics = ['BNPAFRPP', 'BNPAFRPPXXX', 'DEUTDEFF', 'DEUTDEFFXXX', 'SOGEFRPP', 'SOGEFRPPXXX', null];
        $statuses = ['active', 'pending', 'archived'];

        return [
            'name' => fake($division->language ?? 'fr-FR')->company,
            'timezone' => $division->timezone,
            'division_id' => $division->id,
            'external_id' => null,
            'registration_number' => fake($division->language)->creditCardNumber(),
            'registration_country' => $division->country,
            'website' => fake($division->language)->url(),
            'iban' => fake($division->language)->iban($division->country),
            'bic' => fake()->randomElement($bics),
            'vat_number' => fake($division->language)->ean13(),
            'representative_id' => null,
            'available_languages' => [$division->language],
            'status' => fake()->randomElement($statuses),
            'company_number' => strtoupper($division->country).fake()->numerify('#########'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
