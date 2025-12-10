<?php

namespace App\Integrations\HRTools\Database\factories;

use App\Enums\Languages;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $financer = Financer::inRandomOrder()->first();
        if (! $financer instanceof Financer) {
            $financer = Financer::factory()->create();
        }

        // Get available languages from financer or use default languages
        $availableLanguages = ($financer instanceof Financer && ! empty($financer->available_languages))
            ? $financer->available_languages
            : [Languages::FRENCH, Languages::ENGLISH];

        // Generate translatable content for each language
        $name = [];
        $description = [];
        $url = [];

        foreach ($availableLanguages as $lang) {
            $name[$lang] = $this->faker->name();
            $description[$lang] = $this->faker->text();
            $url[$lang] = $this->faker->url();
        }

        return [
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'logo_url' => $this->faker->url(),
            'financer_id' => $financer instanceof Financer ? $financer->id : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
