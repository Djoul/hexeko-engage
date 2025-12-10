<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Enums\Languages;
use App\Enums\Security\AuthorizationMode;
use App\Integrations\InternalCommunication\Actions\CreateDefaultTagsAction;
use App\Integrations\InternalCommunication\Enums\TagsDefault;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['int_communication_rh_tags'], scope: 'test')]
#[Group('tag')]
#[Group('internal-communication')]
#[Group('financer')]
class CreateDefaultTagsActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateDefaultTagsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = resolve(CreateDefaultTagsAction::class);
    }

    /**
     * Create a financer and hydrate authorization context
     */
    private function createFinancerWithContext(): Financer
    {
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer->id],
            [$financer->division_id],
            [],
            $financer->id
        );

        return $financer;
    }

    #[Test]
    public function it_creates_all_default_tags_for_new_financer(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();
        $initialTagCount = Tag::where('financer_id', $financer->id)->count();

        // Act
        $this->action->handle($financer);

        // Assert
        $finalTagCount = Tag::where('financer_id', $financer->id)->count();
        $expectedTagCount = count(TagsDefault::getDefinitions());

        $this->assertEquals($initialTagCount + $expectedTagCount, $finalTagCount);
        $this->assertEquals(10, $finalTagCount); // 10 default tags
    }

    #[Test]
    public function it_creates_tags_with_correct_translations(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();

        // Act
        $this->action->handle($financer);

        // Assert
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
    public function it_is_idempotent_when_tags_already_exist(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();

        // Act - Create tags first time
        $this->action->handle($financer);
        $firstRunCount = Tag::where('financer_id', $financer->id)->count();

        // Act - Create tags second time
        $this->action->handle($financer);
        $secondRunCount = Tag::where('financer_id', $financer->id)->count();

        // Assert - Should have same number of tags
        $this->assertEquals($firstRunCount, $secondRunCount);
        $this->assertEquals(10, $secondRunCount);
    }

    #[Test]
    public function it_reuses_existing_tags_with_legacy_labels(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();

        // Create a tag with legacy English label
        $existingTag = Tag::create([
            'financer_id' => $financer->id,
            'label' => [
                Languages::ENGLISH => 'News', // Legacy label
            ],
        ]);

        // Act
        $this->action->handle($financer);

        // Assert
        $updatedTag = Tag::find($existingTag->id);
        $this->assertNotNull($updatedTag);
        $this->assertEquals('General Announcements', $updatedTag->getTranslation('label', Languages::ENGLISH));
        $this->assertEquals('Annonces générales', $updatedTag->getTranslation('label', Languages::FRENCH_BELGIUM));

        // Should still have only 10 tags total (no duplicate)
        $totalTags = Tag::where('financer_id', $financer->id)->count();
        $this->assertEquals(10, $totalTags);
    }

    #[Test]
    public function it_handles_financer_with_no_prior_tags(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();

        // Verify no tags exist initially
        $this->assertEquals(0, Tag::where('financer_id', $financer->id)->count());

        // Act
        $this->action->handle($financer);

        // Assert
        $this->assertEquals(10, Tag::where('financer_id', $financer->id)->count());
    }

    #[Test]
    public function it_creates_tags_only_for_specific_financer(): void
    {
        // Arrange
        $financer1 = $this->createFinancerWithContext();
        $financer2 = Financer::withoutEvents(fn () => Financer::factory()->create());

        // Act
        $this->action->handle($financer1);

        // Assert
        $this->assertEquals(10, Tag::where('financer_id', $financer1->id)->count());
        $this->assertEquals(0, Tag::where('financer_id', $financer2->id)->count());
    }

    #[Test]
    public function it_updates_existing_tag_translations_without_duplicating(): void
    {
        // Arrange
        $financer = $this->createFinancerWithContext();

        // Create partial tag
        $existingTag = Tag::create([
            'financer_id' => $financer->id,
            'label' => [
                Languages::ENGLISH => 'Training',
            ],
        ]);

        $existingTagId = $existingTag->id;

        // Act
        $this->action->handle($financer);

        // Assert
        $updatedTag = Tag::find($existingTagId);
        $this->assertNotNull($updatedTag);
        $this->assertEquals('Training', $updatedTag->getTranslation('label', Languages::ENGLISH));
        $this->assertEquals('Formations', $updatedTag->getTranslation('label', Languages::FRENCH_BELGIUM));

        // Should have 10 total tags (no duplicate)
        $totalTags = Tag::where('financer_id', $financer->id)->count();
        $this->assertEquals(10, $totalTags);
    }
}
