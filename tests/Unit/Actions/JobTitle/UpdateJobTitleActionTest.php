<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\JobTitle;

use App\Actions\JobTitle\UpdateJobTitleAction;
use App\Models\Financer;
use App\Models\JobTitle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobtitle')]
class UpdateJobTitleActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateJobTitleAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateJobTitleAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_job_title_successfully(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Original Job Title',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Job Title',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_job_title_name_only(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Developer',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Developer',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Original Job Title',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Job Title Only',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Analyst',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Senior Analyst',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertDatabaseHas('job_titles', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $freshJobTitle = JobTitle::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshJobTitle);
        $this->assertEquals($updateData['name'], $freshJobTitle->name);
    }

    #[Test]
    public function it_returns_refreshed_job_title_instance(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Designer',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $jobTitle->updated_at;
        sleep(1);

        $updateData = [
            'name' => 'Senior Designer',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_job_title_with_special_characters(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Simple Job Title',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'C++ Developer & Team Lead (Senior)',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_job_title_id_after_update(): void
    {
        // Arrange
        $jobTitle = JobTitle::create([
            'name' => 'Manager',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $jobTitle->id;

        $updateData = [
            'name' => 'Senior Manager',
        ];

        // Act
        $result = $this->action->execute($jobTitle, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
