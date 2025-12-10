<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tag;

use App\Actions\Tag\CreateTagAction;
use App\Models\Financer;
use App\Models\Tag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('tag')]
class CreateTagActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateTagAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateTagAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_tag_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Marketing',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_tag_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Sales',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_tag_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Engineering',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('tags', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $tag = Tag::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($tag);
        $this->assertEquals($data['name'], $tag->name);
    }

    #[Test]
    public function it_returns_refreshed_tag_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'HR',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertNotNull($result->id);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_tag_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Remote',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
        $this->assertNotEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'Tag & Label (Special)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Tag::class, $result);
        $this->assertEquals($data['name'], $result->name);
    }
}
