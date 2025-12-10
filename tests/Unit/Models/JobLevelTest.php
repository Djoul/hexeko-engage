<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\JobLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('joblevel')]
class JobLevelTest extends TestCase
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
        $jobLevel = new JobLevel;

        $this->assertTrue($jobLevel->getIncrementing() === false);
        $this->assertEquals('string', $jobLevel->getKeyType());
    }

    #[Test]
    public function it_can_create_a_job_level(): void
    {
        // Arrange

        // Act
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Senior',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $jobLevel);
        $this->assertEquals('Senior', $jobLevel->name);
        $this->assertEquals($this->financer->id, $jobLevel->financer_id);
    }

    #[Test]
    public function it_can_update_a_job_level(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Junior',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Senior',
        ];

        // Act
        $jobLevel->update($updatedData);

        // Assert
        $this->assertEquals('Senior', $jobLevel->name);
    }

    #[Test]
    public function it_can_soft_delete_a_job_level(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Job Level to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $jobLevel->delete();

        // Assert
        $this->assertSoftDeleted('job_levels', ['id' => $jobLevel->id]);
        $this->assertTrue($jobLevel->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_job_level(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Job Level to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $jobLevel->delete();

        // Act
        $jobLevel->restore();

        // Assert
        $this->assertFalse($jobLevel->trashed());
        $this->assertDatabaseHas('job_levels', [
            'id' => $jobLevel->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Job Level with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $jobLevel->financer;

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
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $jobLevel->created_by);
        $this->assertDatabaseHas('job_levels', [
            'id' => $jobLevel->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($jobLevel->created_by);
        $this->assertDatabaseHas('job_levels', [
            'id' => $jobLevel->id,
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
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobLevel->update([
            'name' => 'Updated Job Level Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $jobLevel->created_by);
        $this->assertEquals($updater->id, $jobLevel->updated_by);
        $this->assertDatabaseHas('job_levels', [
            'id' => $jobLevel->id,
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
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $jobLevel->creator);
        $this->assertEquals($creator->id, $jobLevel->creator->id);
        $this->assertEquals($creator->name, $jobLevel->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobLevel->update([
            'name' => 'Updated Job Level',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $jobLevel->updater);
        $this->assertEquals($updater->id, $jobLevel->updater->id);
        $this->assertEquals($updater->name, $jobLevel->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($jobLevel->wasCreatedBy($creator));
        $this->assertFalse($jobLevel->wasCreatedBy($otherUser));
        $this->assertFalse($jobLevel->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $jobLevel = JobLevel::factory()->create([
            'name' => 'Job Level to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobLevel->update([
            'name' => 'Updated Job Level',
        ]);

        // Assert
        $this->assertTrue($jobLevel->wasUpdatedBy($updater));
        $this->assertFalse($jobLevel->wasUpdatedBy($creator));
        $this->assertFalse($jobLevel->wasUpdatedBy($otherUser));
        $this->assertFalse($jobLevel->wasUpdatedBy(null));
    }
}
