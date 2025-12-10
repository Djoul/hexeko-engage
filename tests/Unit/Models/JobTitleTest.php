<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobtitle')]
class JobTitleTest extends TestCase
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
        $jobTitle = new JobTitle;

        $this->assertTrue($jobTitle->getIncrementing() === false);
        $this->assertEquals('string', $jobTitle->getKeyType());
    }

    #[Test]
    public function it_can_create_a_job_title(): void
    {
        // Arrange

        // Act
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Software Engineer',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $jobTitle);
        $this->assertEquals('Software Engineer', $jobTitle->name);
        $this->assertEquals($this->financer->id, $jobTitle->financer_id);
    }

    #[Test]
    public function it_can_update_a_job_title(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Junior Developer',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Senior Developer',
        ];

        // Act
        $jobTitle->update($updatedData);

        // Assert
        $this->assertEquals('Senior Developer', $jobTitle->name);
    }

    #[Test]
    public function it_can_soft_delete_a_job_title(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Job Title to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $jobTitle->delete();

        // Assert
        $this->assertSoftDeleted('job_titles', ['id' => $jobTitle->id]);
        $this->assertTrue($jobTitle->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_job_title(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Job Title to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $jobTitle->delete();

        // Act
        $jobTitle->restore();

        // Assert
        $this->assertFalse($jobTitle->trashed());
        $this->assertDatabaseHas('job_titles', [
            'id' => $jobTitle->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Job Title with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $jobTitle->financer;

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
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $jobTitle->created_by);
        $this->assertDatabaseHas('job_titles', [
            'id' => $jobTitle->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($jobTitle->created_by);
        $this->assertDatabaseHas('job_titles', [
            'id' => $jobTitle->id,
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
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobTitle->update([
            'name' => 'Updated Job Title Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $jobTitle->created_by);
        $this->assertEquals($updater->id, $jobTitle->updated_by);
        $this->assertDatabaseHas('job_titles', [
            'id' => $jobTitle->id,
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
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $jobTitle->creator);
        $this->assertEquals($creator->id, $jobTitle->creator->id);
        $this->assertEquals($creator->name, $jobTitle->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobTitle->update([
            'name' => 'Updated Job Title',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $jobTitle->updater);
        $this->assertEquals($updater->id, $jobTitle->updater->id);
        $this->assertEquals($updater->name, $jobTitle->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($jobTitle->wasCreatedBy($creator));
        $this->assertFalse($jobTitle->wasCreatedBy($otherUser));
        $this->assertFalse($jobTitle->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $jobTitle = JobTitle::factory()->create([
            'name' => 'Job Title to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $jobTitle->update([
            'name' => 'Updated Job Title',
        ]);

        // Assert
        $this->assertTrue($jobTitle->wasUpdatedBy($updater));
        $this->assertFalse($jobTitle->wasUpdatedBy($creator));
        $this->assertFalse($jobTitle->wasUpdatedBy($otherUser));
        $this->assertFalse($jobTitle->wasUpdatedBy(null));
    }
}
