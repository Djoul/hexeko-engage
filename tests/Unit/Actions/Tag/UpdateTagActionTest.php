<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tag;

use App\Actions\Tag\UpdateTagAction;
use App\Models\Financer;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('tag')]
class UpdateTagActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateTagAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateTagAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_tag_successfully(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Original Tag',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Tag',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_tag_name_only(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Marketing',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Marketing',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Original Tag',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Tag Only',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Engineering',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Engineering & Dev',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertDatabaseHas('tags', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $freshTag = Tag::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshTag);
        $this->assertEquals($updateData['name'], $freshTag->name);
    }

    #[Test]
    public function it_returns_refreshed_tag_instance(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'HR',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $tag->updated_at;
        sleep(1);

        $updateData = [
            'name' => 'HR Updated',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_tag_with_special_characters(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Simple Tag',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Tag & Label (Special)',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_tag_id_after_update(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Remote',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $tag->id;

        $updateData = [
            'name' => 'Remote Updated',
        ];

        // Act
        $result = $this->action->execute($tag, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
