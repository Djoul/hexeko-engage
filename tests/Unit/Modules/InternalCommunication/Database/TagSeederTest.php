<?php

namespace Tests\Unit\Modules\InternalCommunication\Database;

use App\Enums\Languages;
use App\Enums\Security\AuthorizationMode;
use App\Integrations\InternalCommunication\Database\factories\TagFactory;
use App\Integrations\InternalCommunication\Database\seeders\TagSeeder;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['int_communication_rh_tags'], scope: 'test')]
#[Group('internal-communication')]
class TagSeederTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Hydrate authorization context for a given financer
     */
    private function hydrateContext(Financer $financer): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer->id],
            [$financer->division_id],
            [],
            $financer->id  // Set current financer for global scopes
        );

        // Set Context for global scopes
        Context::add('financer_id', $financer->id);
        Context::add('accessible_financers', [$financer->id]);
        Context::add('accessible_divisions', [$financer->division_id]);
    }

    #[Test]
    public function it_updates_existing_tags_in_place(): void
    {
        // Create financer without triggering observer (we want to manually create legacy tags)
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Hydrate context for Tag queries
        $this->hydrateContext($financer);

        $legacyTranslations = [
            [
                Languages::ENGLISH => 'News',
                Languages::FRENCH_BELGIUM => 'Actualités',
                Languages::DUTCH_BELGIUM => 'Nieuws',
                Languages::PORTUGUESE => 'Notícias',
            ],
            [
                Languages::ENGLISH => 'Announcement',
                Languages::FRENCH_BELGIUM => 'Annonce',
                Languages::DUTCH_BELGIUM => 'Aankondiging',
                Languages::PORTUGUESE => 'Anúncio',
            ],
            [
                Languages::ENGLISH => 'Event',
                Languages::FRENCH_BELGIUM => 'Événement',
                Languages::DUTCH_BELGIUM => 'Evenement',
                Languages::PORTUGUESE => 'Evento',
            ],
            [
                Languages::ENGLISH => 'HR',
                Languages::FRENCH_BELGIUM => 'RH',
                Languages::DUTCH_BELGIUM => 'HR',
                Languages::PORTUGUESE => 'RH',
            ],
            [
                Languages::ENGLISH => 'Training',
                Languages::FRENCH_BELGIUM => 'Formation',
                Languages::DUTCH_BELGIUM => 'Opleiding',
                Languages::PORTUGUESE => 'Formação',
            ],
        ];

        $existingTags = [];
        foreach ($legacyTranslations as $translations) {
            $tag = resolve(TagFactory::class)
                ->for($financer, 'financer')
                ->create([
                    'label' => $translations,
                ]);

            $existingTags[$translations[Languages::ENGLISH]] = $tag->id;
        }

        resolve(TagSeeder::class)->run();

        // Re-hydrate context after seeder (seeder may have changed it)
        $this->hydrateContext($financer);

        $this->assertSame(10, Tag::where('financer_id', $financer->id)->count());

        $this->assertSame(
            'General Announcements',
            Tag::findOrFail($existingTags['News'])->getTranslation('label', Languages::ENGLISH)
        );

        $this->assertSame(
            'Company News',
            Tag::findOrFail($existingTags['Announcement'])->getTranslation('label', Languages::ENGLISH)
        );

        $this->assertSame(
            'Internal Events',
            Tag::findOrFail($existingTags['Event'])->getTranslation('label', Languages::ENGLISH)
        );

        $this->assertSame(
            'HR & Career',
            Tag::findOrFail($existingTags['HR'])->getTranslation('label', Languages::ENGLISH)
        );

        $this->assertSame(
            'Training',
            Tag::findOrFail($existingTags['Training'])->getTranslation('label', Languages::ENGLISH)
        );
    }

    #[Test]
    public function it_is_idempotent_when_reseeded(): void
    {
        $financer = Financer::factory()->create();
        $this->hydrateContext($financer);

        resolve(TagSeeder::class)->run();

        // Re-hydrate context after seeder (seeder may have changed it)
        $this->hydrateContext($financer);

        $this->assertSame(10, Tag::where('financer_id', $financer->id)->count());

        $firstRunIds = Tag::where('financer_id', $financer->id)
            ->pluck('id')
            ->sort()
            ->values();

        resolve(TagSeeder::class)->run();

        // Re-hydrate context after seeder
        $this->hydrateContext($financer);

        $this->assertSame(10, Tag::where('financer_id', $financer->id)->count());

        $secondRunIds = Tag::where('financer_id', $financer->id)
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertEquals($firstRunIds, $secondRunIds);
    }
}
