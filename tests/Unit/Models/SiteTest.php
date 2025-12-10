<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('site')]
class SiteTest extends TestCase
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
        $site = new Site;

        $this->assertTrue($site->getIncrementing() === false);
        $this->assertEquals('string', $site->getKeyType());
    }

    #[Test]
    public function it_can_create_a_site(): void
    {
        // Arrange

        // Act
        $site = Site::factory()->create([
            'name' => 'Test Site',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(Site::class, $site);
        $this->assertEquals('Test Site', $site->name);
        $this->assertEquals($this->financer->id, $site->financer_id);
    }

    #[Test]
    public function it_can_update_a_site(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Original Site',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Updated Site',
        ];

        // Act
        $site->update($updatedData);

        // Assert
        $this->assertEquals('Updated Site', $site->name);
    }

    #[Test]
    public function it_can_soft_delete_a_site(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Site to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $site->delete();

        // Assert
        $this->assertSoftDeleted('sites', ['id' => $site->id]);
        $this->assertTrue($site->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_site(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Site to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $site->delete();

        // Act
        $site->restore();

        // Assert
        $this->assertFalse($site->trashed());
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Site with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $site->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_has_many_users(): void
    {
        // Arrange
        $site = Site::factory()->create([
            'name' => 'Test Site',
            'financer_id' => $this->financer->id,
        ]);

        User::factory(5)->create()->each(function ($user) use ($site): void {
            $site->users()->attach($user->id);
        });

        // Act
        $retrievedUsers = $site->users;

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
        $site = Site::factory()->create([
            'name' => 'Site with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $site->created_by);
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $site = Site::factory()->create([
            'name' => 'Site without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($site->created_by);
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
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
        $site = Site::factory()->create([
            'name' => 'Site to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $site->update([
            'name' => 'Updated Site Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $site->created_by);
        $this->assertEquals($updater->id, $site->updated_by);
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
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
        $site = Site::factory()->create([
            'name' => 'Site with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $site->creator);
        $this->assertEquals($creator->id, $site->creator->id);
        $this->assertEquals($creator->name, $site->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $site = Site::factory()->create([
            'name' => 'Site with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $site->update([
            'name' => 'Updated Site',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $site->updater);
        $this->assertEquals($updater->id, $site->updater->id);
        $this->assertEquals($updater->name, $site->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $site = Site::factory()->create([
            'name' => 'Site to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($site->wasCreatedBy($creator));
        $this->assertFalse($site->wasCreatedBy($otherUser));
        $this->assertFalse($site->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $site = Site::factory()->create([
            'name' => 'Site to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $site->update([
            'name' => 'Updated Site',
        ]);

        // Assert
        $this->assertTrue($site->wasUpdatedBy($updater));
        $this->assertFalse($site->wasUpdatedBy($creator));
        $this->assertFalse($site->wasUpdatedBy($otherUser));
        $this->assertFalse($site->wasUpdatedBy(null));
    }
}
