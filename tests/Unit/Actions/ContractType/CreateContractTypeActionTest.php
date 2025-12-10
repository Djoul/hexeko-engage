<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\ContractType;

use App\Actions\ContractType\CreateContractTypeAction;
use App\Models\ContractType;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('contract_type')]
class CreateContractTypeActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateContractTypeAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateContractTypeAction;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_creates_a_contract_type_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Permanent Contract',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_contract_type_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Minimal Contract Type',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_contract_type_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Fixed-term Contract',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('contract_types', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        $contractType = ContractType::withoutGlobalScopes()->find($result->id);
        $this->assertNotNull($contractType);
        $this->assertEquals($data['name'], $contractType->name);
    }

    #[Test]
    public function it_returns_refreshed_contract_type_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'Temporary Contract',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertNotNull($result->id);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_contract_type_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Freelance Contract',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
        $this->assertNotEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'Contract & Agreement (Special)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(ContractType::class, $result);
        $this->assertEquals($data['name'], $result->name);
    }
}
