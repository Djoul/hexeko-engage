<?php

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Actions\CreateTagAction;
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
class CreateTagActionTest extends TestCase
{
    private CreateTagAction $createTagAction;

    private MockObject $tagServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a mock of the TagService
        $this->tagServiceMock = $this->createMock(TagService::class);

        // Inject the mock into the CreateTagAction
        $this->createTagAction = new CreateTagAction($this->tagServiceMock);
    }

    #[Test]
    public function it_creates_a_tag_using_tag_service(): void
    {
        // Arrange
        $financer = Financer::factory()->create();

        $tagData = [
            'financer_id' => $financer->id,
            'label' => [
                'en' => 'Test Tag',
                'fr' => 'Tag de Test',
                'nl' => 'Test Tag',
            ],
        ];

        $expectedTag = new Tag;
        $expectedTag->financer_id = $financer->id;
        $expectedTag->label = $tagData['label'];

        // Set up the mock to return the expected tag when create is called with the tag data
        $this->tagServiceMock
            ->expects($this->once())
            ->method('create')
            ->with($tagData)
            ->willReturn($expectedTag);

        // Act
        $result = $this->createTagAction->handle($tagData);

        // Assert
        $this->assertSame($expectedTag, $result);
    }
}
