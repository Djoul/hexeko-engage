<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\JobLevel;

use App\Actions\JobLevel\UpdateJobLevelAction;
use App\Models\Financer;
use App\Models\JobLevel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('joblevel')]
class UpdateJobLevelActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateJobLevelAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateJobLevelAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_job_level_successfully(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Original Job Level',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Job Level',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_job_level_name_only(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Senior',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Senior',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Original Job Level',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Job Level Only',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Junior',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Senior Junior',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertDatabaseHas('job_levels', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $freshJobLevel = JobLevel::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshJobLevel);
        $this->assertEquals($updateData['name'], $freshJobLevel->name);
    }

    #[Test]
    public function it_returns_refreshed_job_level_instance(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Lead',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $jobLevel->updated_at;
        sleep(1);

        $updateData = [
            'name' => 'Lead Updated',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_job_level_with_special_characters(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Simple Job Level',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Senior Level (C-Suite & Executive)',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_job_level_id_after_update(): void
    {
        // Arrange
        $jobLevel = JobLevel::create([
            'name' => 'Principal',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $jobLevel->id;

        $updateData = [
            'name' => 'Principal Updated',
        ];

        // Act
        $result = $this->action->execute($jobLevel, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
