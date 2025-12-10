<?php

namespace App\Integrations\HRTools\Database\Seeders;

use App\Enums\Languages;
use App\Models\Financer;
use DB;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Nonstandard\Uuid;

class HRToolsLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // seed 10 links
        foreach (Financer::withoutGlobalScopes()->get() as $financer) {
            for ($i = 0; $i < 10; $i++) {
                // Get available languages from financer or use default languages
                $availableLanguages = empty($financer->available_languages)
                    ? [Languages::FRENCH, Languages::ENGLISH]
                    : $financer->available_languages;

                // Create translatable content for each language
                $name = [];
                $description = [];
                $url = [];

                foreach ($availableLanguages as $lang) {
                    $name[$lang] = 'Link '.$i.' ('.$lang.')';
                    $description[$lang] = 'Description '.$i.' ('.$lang.')';
                    $url[$lang] = 'https://link'.$i.'.com/'.$lang;
                }

                DB::table('int_outils_rh_links')->insert([
                    'id' => Uuid::uuid4(),
                    'name' => json_encode($name),
                    'position' => $i + 1,
                    'description' => json_encode($description),
                    'url' => json_encode($url),
                    'logo_url' => $i % 2 !== 0 ? null : 'https://images.scalebranding.com/up-logo-4ea65e1c-0b75-4bbd-9d2f-0e9413890258.jpg',
                    'api_endpoint' => '/api/link'.$i,
                    'front_endpoint' => '/link'.$i,
                    'financer_id' => $financer->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
