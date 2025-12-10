<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\WorkMode;

use App\Actions\WorkMode\UpdateWorkModeAction;
use App\Models\Financer;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('workmode')]
class UpdateWorkModeActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateWorkModeAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateWorkModeAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_work_mode_successfully(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Original Work Mode',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Work Mode',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_work_mode_name_only(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Remote',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Remote',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Original Work Mode',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Work Mode Only',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Office',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Office & Remote',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertDatabaseHas('work_modes', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $freshWorkMode = WorkMode::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshWorkMode);
        $this->assertEquals($updateData['name'], $freshWorkMode->name);
    }

    #[Test]
    public function it_returns_refreshed_work_mode_instance(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Flexible',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $workMode->updated_at;
        sleep(1);

        $updateData = [
            'name' => 'Flexible Updated',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_work_mode_with_special_characters(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Simple Work Mode',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Work & Life Balance (Special)',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertInstanceOf(WorkMode::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_work_mode_id_after_update(): void
    {
        // Arrange
        $workMode = WorkMode::create([
            'name' => 'Contract',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $workMode->id;

        $updateData = [
            'name' => 'Contract Updated',
        ];

        // Act
        $result = $this->action->execute($workMode, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
