<?php

namespace Database\Seeders;

use App\Integrations\HRTools\Models\Link;
use App\Models\DemoEntity;
use App\Models\Financer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinksDemoEntitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all demo financers
        $demoFinancerIds = DemoEntity::where('entity_type', Financer::class)
            ->pluck('entity_id');
        // Get all Links that belong to demo financers
        $links = Link::whereIn('financer_id', $demoFinancerIds)->get();

        if ($links->isEmpty()) {
            $this->command->info('  No Links found for demo financers.');

            return;
        }

        // Prepare demo entities data for Links
        $demoEntities = [];
        $now = now();

        foreach ($links as $link) {
            // Check if this Link is already in demo_entities
            $exists = DemoEntity::where('entity_type', Link::class)
                ->where('entity_id', $link->id)
                ->exists();

            if (! $exists) {
                $demoEntities[] = [
                    'id' => (string) Str::uuid(),
                    'entity_type' => Link::class,
                    'entity_id' => $link->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($demoEntities !== []) {
            // Insert in chunks to avoid memory issues
            $chunks = array_chunk($demoEntities, 100);
            foreach ($chunks as $chunk) {
                DB::table('demo_entities')->insert($chunk);
            }

            $this->command->info('   ✅ Added '.count($demoEntities).' Links to demo_entities table.');
        } else {
            $this->command->info('All Links are already in demo_entities table.');
        }
    }
}
