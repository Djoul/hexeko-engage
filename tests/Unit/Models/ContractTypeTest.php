<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ContractType;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('contract_type')]
class ContractTypeTest extends TestCase
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
        $contractType = new ContractType;

        $this->assertTrue($contractType->getIncrementing() === false);
        $this->assertEquals('string', $contractType->getKeyType());
    }

    #[Test]
    public function it_can_create_a_contract_type(): void
    {
        // Arrange

        // Act
        $contractType = ContractType::factory()->create([
            'name' => 'Permanent Contract',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertInstanceOf(ContractType::class, $contractType);
        $this->assertEquals('Permanent Contract', $contractType->name);
        $this->assertEquals($this->financer->id, $contractType->financer_id);
    }

    #[Test]
    public function it_can_update_a_contract_type(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Original Contract Type',
            'financer_id' => $this->financer->id,
        ]);

        $updatedData = [
            'name' => 'Updated Contract Type',
        ];

        // Act
        $contractType->update($updatedData);

        // Assert
        $this->assertEquals('Updated Contract Type', $contractType->name);
    }

    #[Test]
    public function it_can_soft_delete_a_contract_type(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Contract Type to Delete',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $contractType->delete();

        // Assert
        $this->assertSoftDeleted('contract_types', ['id' => $contractType->id]);
        $this->assertTrue($contractType->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_contract_type(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Contract Type to Restore',
            'financer_id' => $this->financer->id,
        ]);
        $contractType->delete();

        // Act
        $contractType->restore();

        // Assert
        $this->assertFalse($contractType->trashed());
        $this->assertDatabaseHas('contract_types', [
            'id' => $contractType->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $contractType = ContractType::create([
            'name' => 'Contract Type with Financer',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $contractType->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_has_many_users(): void
    {
        // Arrange
        $contractType = ContractType::factory()->create([
            'name' => 'Test Contract Type',
            'financer_id' => $this->financer->id,
        ]);

        User::factory(5)->create()->each(function ($user) use ($contractType): void {
            $contractType->users()->attach($user->id);
        });

        // Act
        $retrievedUsers = $contractType->users;

        // Assert
        $this->assertCount(5, $retrievedUsers);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type with creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $contractType->created_by);
        $this->assertDatabaseHas('contract_types', [
            'id' => $contractType->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type without creator',
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($contractType->created_by);
        $this->assertDatabaseHas('contract_types', [
            'id' => $contractType->id,
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
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type to update',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $contractType->update([
            'name' => 'Updated Contract Type Name',
        ]);

        // Assert
        $this->assertEquals($creator->id, $contractType->created_by);
        $this->assertEquals($updater->id, $contractType->updated_by);
        $this->assertDatabaseHas('contract_types', [
            'id' => $contractType->id,
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
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type with creator relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $contractType->creator);
        $this->assertEquals($creator->id, $contractType->creator->id);
        $this->assertEquals($creator->name, $contractType->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type with updater relationship',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $contractType->update([
            'name' => 'Updated Contract Type',
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $contractType->updater);
        $this->assertEquals($updater->id, $contractType->updater->id);
        $this->assertEquals($updater->name, $contractType->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type to check creator',
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($contractType->wasCreatedBy($creator));
        $this->assertFalse($contractType->wasCreatedBy($otherUser));
        $this->assertFalse($contractType->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $contractType = ContractType::factory()->create([
            'name' => 'Contract Type to check updater',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $contractType->update([
            'name' => 'Updated Contract Type',
        ]);

        // Assert
        $this->assertTrue($contractType->wasUpdatedBy($updater));
        $this->assertFalse($contractType->wasUpdatedBy($creator));
        $this->assertFalse($contractType->wasUpdatedBy($otherUser));
        $this->assertFalse($contractType->wasUpdatedBy(null));
    }
}
