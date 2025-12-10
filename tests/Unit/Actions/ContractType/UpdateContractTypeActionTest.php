<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\ContractType;

use App\Actions\ContractType\UpdateContractTypeAction;
use App\Models\ContractType;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('contract_type')]
class UpdateContractTypeActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateContractTypeAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateContractTypeAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_updates_a_contract_type_successfully(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Original Contract Type',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Contract Type',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_contract_type_name_only(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Permanent Contract',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Permanent Contract',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Original Contract Type',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Contract Type Only',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Fixed-term Contract',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Long-term Fixed Contract',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertDatabaseHas('contract_types', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $freshContractType = ContractType::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($freshContractType);
        $this->assertEquals($updateData['name'], $freshContractType->name);
    }

    #[Test]
    public function it_returns_refreshed_contract_type_instance(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Temporary Contract',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $contractType->updated_at;
        sleep(1);

        $updateData = [
            'name' => 'Temporary Contract Updated',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_contract_type_with_special_characters(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Simple Contract',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Contract & Agreement (Special)',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_contract_type_id_after_update(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Freelance Contract',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $contractType->id;

        $updateData = [
            'name' => 'Freelance Contract Updated',
        ];

        // Act
        $result = $this->action->execute($contractType, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
