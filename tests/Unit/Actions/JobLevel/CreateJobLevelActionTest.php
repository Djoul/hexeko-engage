<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\JobLevel;

use App\Actions\JobLevel\CreateJobLevelAction;
use App\Models\Financer;
use App\Models\JobLevel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('joblevel')]
class CreateJobLevelActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateJobLevelAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateJobLevelAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_job_level_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Senior',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_job_level_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Junior',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_job_level_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Intermediate',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('job_levels', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_job_level_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'Principal',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobLevel::class, $result);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_job_level_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Director',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'Senior Level (C-Suite & Executive)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($data['name'], $result->name);
    }
}
