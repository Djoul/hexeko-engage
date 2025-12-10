<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('tag')]
class TagTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $tag = new Tag;

        $this->assertTrue($tag->getIncrementing() === false);
        $this->assertEquals('string', $tag->getKeyType());
    }

    #[Test]
    public function it_can_create_a_tag(): void
    {
        // Arrange

        // Act
        $tag = Tag::factory()->create([
            'name' => 'Marketing',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals('Marketing', $tag->name);
        $this->assertEquals($this->financer->id, $tag->financer_id);
    }

    #[Test]
    public function it_can_update_a_tag(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Original Tag',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Updated Tag',
        ];

        // Act
        $tag->update($updatedData);

        // Assert
        $this->assertEquals('Updated Tag', $tag->name);
    }

    #[Test]
    public function it_can_soft_delete_a_tag(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Tag to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $tag->delete();

        // Assert
        $this->assertSoftDeleted('tags', ['id' => $tag->id]);
        $this->assertTrue($tag->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_tag(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Tag to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $tag->delete();

        // Act
        $tag->restore();

        // Assert
        $this->assertFalse($tag->trashed());
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $tag = Tag::create([
            'name' => 'Tag with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $tag->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_has_many_users(): void
    {
        // Arrange
        $tag = Tag::factory()->create([
            'name' => 'Test Tag',
            'financer_id' => $this->financer->id,
        ]);

        User::factory(5)->create()->each(function ($user) use ($tag): void {
            $tag->users()->attach($user->id);
        });

        // Act
        $retrievedUsers = $tag->users;

        // Assert
        $this->assertCount(5, $retrievedUsers);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $tag = Tag::factory()->create([
            'name' => 'Tag with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $tag->created_by);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $tag = Tag::factory()->create([
            'name' => 'Tag without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($tag->created_by);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $tag = Tag::factory()->create([
            'name' => 'Tag to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $tag->update([
            'name' => 'Updated Tag Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $tag->created_by);
        $this->assertEquals($updater->id, $tag->updated_by);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();

        Auth::login($creator);
        $tag = Tag::factory()->create([
            'name' => 'Tag with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $tag->creator);
        $this->assertEquals($creator->id, $tag->creator->id);
        $this->assertEquals($creator->name, $tag->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $tag = Tag::factory()->create([
            'name' => 'Tag with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $tag->update([
            'name' => 'Updated Tag',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $tag->updater);
        $this->assertEquals($updater->id, $tag->updater->id);
        $this->assertEquals($updater->name, $tag->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $tag = Tag::factory()->create([
            'name' => 'Tag to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($tag->wasCreatedBy($creator));
        $this->assertFalse($tag->wasCreatedBy($otherUser));
        $this->assertFalse($tag->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $tag = Tag::factory()->create([
            'name' => 'Tag to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $tag->update([
            'name' => 'Updated Tag',
        ]);

        // Assert
        $this->assertTrue($tag->wasUpdatedBy($updater));
        $this->assertFalse($tag->wasUpdatedBy($creator));
        $this->assertFalse($tag->wasUpdatedBy($otherUser));
        $this->assertFalse($tag->wasUpdatedBy(null));
    }
}
