<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Actions\DeleteTagAction;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;
use App\Models\Financer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[Group('tag')]
#[Group('article')]
#[Group('internal-communication')]
class DeleteTagActionTest extends TestCase
{
    private DeleteTagAction $deleteTagAction;

    private MockObject $tagServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a mock of the TagService
        $this->tagServiceMock = $this->createMock(TagService::class);

        // Inject the mock into the DeleteTagAction
        $this->deleteTagAction = new DeleteTagAction($this->tagServiceMock);
    }

    #[Test]
    public function it_deletes_a_tag_using_tag_service(): void
    {
        // Arrange
        $financer = Financer::factory()->create();

        $tag = new Tag;
        $tag->financer_id = $financer->id;
        $tag->label = [
            'en' => 'Tag to Delete',
            'fr' => 'Tag à Supprimer',
            'nl' => 'Tag om te Verwijderen',
        ];

        // Set up the mock to return true when delete is called with the tag
        $this->tagServiceMock
            ->expects($this->once())
            ->method('delete')
            ->with($tag)
            ->willReturn(true);

        // Act
        $result = $this->deleteTagAction->handle($tag);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_tag_deletion_fails(): void
    {
        // Arrange
        $financer = Financer::factory()->create();

        $tag = new Tag;
        $tag->financer_id = $financer->id;
        $tag->label = [
            'en' => 'Tag to Delete',
            'fr' => 'Tag à Supprimer',
            'nl' => 'Tag om te Verwijderen',
        ];

        // Set up the mock to return false when delete is called with the tag
        $this->tagServiceMock
            ->expects($this->once())
            ->method('delete')
            ->with($tag)
            ->willReturn(false);

        // Act
        $result = $this->deleteTagAction->handle($tag);

        // Assert
        $this->assertFalse($result);
    }
}
