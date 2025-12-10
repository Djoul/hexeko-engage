<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\JobTitle;

use App\Actions\JobTitle\CreateJobTitleAction;
use App\Models\Financer;
use App\Models\JobTitle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobtitle')]
class CreateJobTitleActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateJobTitleAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateJobTitleAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_job_title_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Software Engineer',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_job_title_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Developer',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_job_title_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Data Analyst',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('job_titles', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_job_title_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'UX Designer',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(JobTitle::class, $result);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_job_title_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Team Lead',
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
            'name' => 'C++ Developer & Team Lead (Senior)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($data['name'], $result->name);
    }
}
