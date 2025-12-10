<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('workmode')]
class WorkModeTest extends TestCase
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
        $workMode = new WorkMode;

        $this->assertTrue($workMode->getIncrementing() === false);
        $this->assertEquals('string', $workMode->getKeyType());
    }

    #[Test]
    public function it_can_create_a_work_mode(): void
    {
        // Arrange

        // Act
        $workMode = WorkMode::factory()->create([
            'name' => 'Test Work Mode',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $workMode);
        $this->assertEquals('Test Work Mode', $workMode->name);
        $this->assertEquals($this->financer->id, $workMode->financer_id);
    }

    #[Test]
    public function it_can_update_a_work_mode(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Original Work Mode',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Updated Work Mode',
        ];

        // Act
        $workMode->update($updatedData);

        // Assert
        $this->assertEquals('Updated Work Mode', $workMode->name);
    }

    #[Test]
    public function it_can_soft_delete_a_work_mode(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Work Mode to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $workMode->delete();

        // Assert
        $this->assertSoftDeleted('work_modes', ['id' => $workMode->id]);
        $this->assertTrue($workMode->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_work_mode(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Work Mode to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $workMode->delete();

        // Act
        $workMode->restore();

        // Assert
        $this->assertFalse($workMode->trashed());
        $this->assertDatabaseHas('work_modes', [
            'id' => $workMode->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Work Mode with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $workMode->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $workMode->created_by);
        $this->assertDatabaseHas('work_modes', [
            'id' => $workMode->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($workMode->created_by);
        $this->assertDatabaseHas('work_modes', [
            'id' => $workMode->id,
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
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $workMode->update([
            'name' => 'Updated Work Mode Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $workMode->created_by);
        $this->assertEquals($updater->id, $workMode->updated_by);
        $this->assertDatabaseHas('work_modes', [
            'id' => $workMode->id,
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
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $workMode->creator);
        $this->assertEquals($creator->id, $workMode->creator->id);
        $this->assertEquals($creator->name, $workMode->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $workMode->update([
            'name' => 'Updated Work Mode',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $workMode->updater);
        $this->assertEquals($updater->id, $workMode->updater->id);
        $this->assertEquals($updater->name, $workMode->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($workMode->wasCreatedBy($creator));
        $this->assertFalse($workMode->wasCreatedBy($otherUser));
        $this->assertFalse($workMode->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $workMode = WorkMode::factory()->create([
            'name' => 'Work Mode to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $workMode->update([
            'name' => 'Updated Work Mode',
        ]);

        // Assert
        $this->assertTrue($workMode->wasUpdatedBy($updater));
        $this->assertFalse($workMode->wasUpdatedBy($creator));
        $this->assertFalse($workMode->wasUpdatedBy($otherUser));
        $this->assertFalse($workMode->wasUpdatedBy(null));
    }
}
