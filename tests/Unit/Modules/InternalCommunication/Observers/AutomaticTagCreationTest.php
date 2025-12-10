<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Observers;

use App\Enums\Languages;
use App\Enums\Security\AuthorizationMode;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['int_communication_rh_tags'], scope: 'test')]
#[Group('unit')]
#[Group('tag')]
#[Group('financer')]
#[Group('internal-communication')]
class AutomaticTagCreationTest extends TestCase
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
            $financer->id
        );
    }

    #[Test]
    public function it_automatically_creates_default_tags_when_financer_is_created(): void
    {
        // Arrange
        $initialTagCount = Tag::count();

        // Act
        $financer = Financer::factory()->create([
            'name' => 'Test Company Auto Tags',
        ]);

        // Hydrate context to query tags (required by HasFinancerScope)
        $this->hydrateContext($financer);

        // Assert
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
            'name' => 'Test Company Auto Tags',
        ]);

        $financerTags = Tag::where('financer_id', $financer->id)->get();
        $this->assertCount(10, $financerTags);

        // Verify total tags increased by 10
        $this->assertEquals($initialTagCount + 10, Tag::count());
    }

    #[Test]
    public function it_creates_tags_with_all_required_translations(): void
    {
        // Act
        $financer = Financer::factory()->create();
        $this->hydrateContext($financer);

        // Assert - Check General Announcements tag
        $generalAnnouncementsTag = Tag::where('financer_id', $financer->id)
            ->where('label->'.Languages::ENGLISH, 'General Announcements')
            ->first();

        $this->assertNotNull($generalAnnouncementsTag);
        $this->assertEquals('General Announcements', $generalAnnouncementsTag->getTranslation('label', Languages::ENGLISH));
        $this->assertEquals('Annonces générales', $generalAnnouncementsTag->getTranslation('label', Languages::FRENCH_BELGIUM));
        $this->assertEquals('Algemene aankondigingen', $generalAnnouncementsTag->getTranslation('label', Languages::DUTCH_BELGIUM));
        $this->assertEquals('Anúncios gerais', $generalAnnouncementsTag->getTranslation('label', Languages::PORTUGUESE));
    }

    #[Test]
    public function it_does_not_create_tags_when_financer_is_updated(): void
    {
        // Arrange
        $financer = Financer::factory()->create();
        $this->hydrateContext($financer);
        $initialTagCount = Tag::where('financer_id', $financer->id)->count();

        // Act
        $financer->update(['name' => 'Updated Company Name']);

        // Assert - Tag count should remain the same
        $finalTagCount = Tag::where('financer_id', $financer->id)->count();
        $this->assertEquals($initialTagCount, $finalTagCount);
    }

    #[Test]
    public function it_creates_unique_tags_for_different_financers(): void
    {
        // Act
        $financer1 = Financer::factory()->create(['name' => 'Company 1']);
        $financer2 = Financer::factory()->create(['name' => 'Company 2']);

        // Hydrate context for financer1
        $this->hydrateContext($financer1);

        // Assert
        $financer1Tags = Tag::where('financer_id', $financer1->id)->count();

        // Switch context to financer2
        $this->hydrateContext($financer2);
        $financer2Tags = Tag::where('financer_id', $financer2->id)->count();

        $this->assertEquals(10, $financer1Tags);
        $this->assertEquals(10, $financer2Tags);

        // Verify tags are separate (no shared tags between financers)
        $sharedTags = Tag::where('financer_id', $financer1->id)
            ->where('financer_id', $financer2->id)
            ->count();
        $this->assertEquals(0, $sharedTags);
    }

    #[Test]
    public function it_works_consistently_with_tag_seeder(): void
    {
        // Arrange
        $financer = Financer::factory()->create();
        $this->hydrateContext($financer);
        $initialTagCount = Tag::where('financer_id', $financer->id)->count();
        $this->assertEquals(10, $initialTagCount);

        // Act - Run seeder again (should be idempotent)
        $this->artisan('db:seed', ['--class' => 'App\\Integrations\\InternalCommunication\\Database\\seeders\\TagSeeder'])
            ->assertExitCode(0);

        // Assert - Tag count should remain the same (idempotent)
        $finalTagCount = Tag::withoutGlobalScopes()->where('financer_id', $financer->id)->count();
        $this->assertEquals($initialTagCount, $finalTagCount);
    }

    #[Test]
    public function it_creates_all_expected_tag_categories(): void
    {
        // Act
        $financer = Financer::factory()->create();
        $this->hydrateContext($financer);

        // Assert - Verify all expected tag categories exist
        $expectedTags = [
            'General Announcements',
            'Company News',
            'Internal Events',
            'HR & Career',
            'Employee Benefits',
            'Wellbeing',
            'Regulations',
            'Training',
            'Culture & Values',
            'Practical Office Life',
        ];

        foreach ($expectedTags as $expectedTag) {
            $tag = Tag::where('financer_id', $financer->id)
                ->where('label->'.Languages::ENGLISH, $expectedTag)
                ->first();

            $this->assertNotNull($tag, "Tag '{$expectedTag}' was not created");
        }
    }
}
