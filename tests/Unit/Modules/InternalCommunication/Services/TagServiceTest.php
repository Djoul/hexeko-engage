<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('article')]
#[Group('internal-communication')]
class TagServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TagService $tagService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagService = app(TagService::class);
    }

    #[Test]
    public function it_can_create_a_tag(): void
    {
        // Arrange
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Set context for HasFinancerScope
        Context::add('financer_id', $financer->id);

        $tagData = [
            'financer_id' => $financer->id,
            'label' => [
                'en' => 'Test Tag',
                'fr' => 'Tag de Test',
                'nl' => 'Test Tag',
            ],
        ];

        // Act
        $tag = $this->tagService->create($tagData);

        // Assert
        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals($financer->id, $tag->financer_id);
        $this->assertEquals('Test Tag', $tag->getTranslatedLabel('en'));
        $this->assertEquals('Tag de Test', $tag->getTranslatedLabel('fr'));
        $this->assertEquals('Test Tag', $tag->getTranslatedLabel('nl'));
    }

    #[Test]
    public function it_can_find_a_tag_by_id(): void
    {
        // Arrange
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Set context for HasFinancerScope
        Context::add('financer_id', $financer->id);

        $tag = Tag::create([
            'financer_id' => $financer->id,
            'label' => [
                'en' => 'Test Tag',
                'fr' => 'Tag de Test',
                'nl' => 'Test Tag',
            ],
        ]);

        // Act
        $foundTag = $this->tagService->find($tag->id);

        // Assert
        $this->assertInstanceOf(Tag::class, $foundTag);
        $this->assertEquals($tag->id, $foundTag->id);
        $this->assertEquals($financer->id, $foundTag->financer_id);
        $this->assertEquals('Test Tag', $foundTag->getTranslatedLabel('en'));
    }

    #[Test]
    public function it_can_update_a_tag(): void
    {
        // Arrange
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Set context for HasFinancerScope
        Context::add('financer_id', $financer->id);

        $tag = Tag::create([
            'financer_id' => $financer->id,
            'label' => [
                'en' => 'Test Tag',
                'fr' => 'Tag de Test',
                'nl' => 'Test Tag',
            ],
        ]);

        $updatedData = [
            'label' => [
                'en' => 'Updated Tag',
                'fr' => 'Tag Mis à Jour',
                'nl' => 'Bijgewerkte Tag',
            ],
        ];

        // Act
        $updatedTag = $this->tagService->update($tag, $updatedData);

        // Assert
        $this->assertEquals('Updated Tag', $updatedTag->getTranslatedLabel('en'));
        $this->assertEquals('Tag Mis à Jour', $updatedTag->getTranslatedLabel('fr'));
        $this->assertEquals('Bijgewerkte Tag', $updatedTag->getTranslatedLabel('nl'));
    }

    #[Test]
    public function it_can_delete_a_tag(): void
    {
        // Arrange
        $financer = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Set context for HasFinancerScope
        Context::add('financer_id', $financer->id);

        $tag = Tag::create([
            'financer_id' => $financer->id,
            'label' => [
                'en' => 'Test Tag',
                'fr' => 'Tag de Test',
                'nl' => 'Test Tag',
            ],
        ]);

        // Act
        $result = $this->tagService->delete($tag);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('int_communication_rh_tags', ['id' => $tag->id]);
    }

    #[Test]
    public function it_can_find_tags_by_financer(): void
    {
        // Arrange - Create financers without triggering observer
        $financer1 = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });
        $financer2 = Financer::withoutEvents(function () {
            return Financer::factory()->create();
        });

        // Set context for financer1 to create its tags
        Context::add('financer_id', $financer1->id);

        Tag::create([
            'financer_id' => $financer1->id,
            'label' => ['en' => 'Tag 1'],
        ]);

        Tag::create([
            'financer_id' => $financer1->id,
            'label' => ['en' => 'Tag 2'],
        ]);

        // Switch context to financer2
        Context::add('financer_id', $financer2->id);

        Tag::create([
            'financer_id' => $financer2->id,
            'label' => ['en' => 'Tag 3'],
        ]);

        // Switch context back to financer1 for the query
        Context::add('financer_id', $financer1->id);

        // Act
        $tags = $this->tagService->findByFinancer($financer1->id);

        // Assert
        $this->assertCount(2, $tags);
        $this->assertEquals('Tag 1', $tags[0]->getTranslatedLabel('en'));
        $this->assertEquals('Tag 2', $tags[1]->getTranslatedLabel('en'));
    }
}
