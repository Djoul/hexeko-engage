<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\ContractType;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use App\Policies\ContractTypePolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('contract_type')]
#[Group('policy')]
class ContractTypePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private ContractTypePolicy $policy;

    private User $user;

    private User $otherUser;

    private ContractType $contractType;

    private ContractType $otherContractType;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ContractTypePolicy;

        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_CONTRACT_TYPE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_CONTRACT_TYPE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_CONTRACT_TYPE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_CONTRACT_TYPE,
            'guard_name' => 'api',
        ]);

        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        $this->contractType = ContractType::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en-GB' => 'Test Contract Type', 'fr-FR' => 'Type de Contrat Test'],
        ]);

        $this->otherContractType = ContractType::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en-GB' => 'Other Contract Type', 'fr-FR' => 'Autre Type de Contrat'],
        ]);

        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_contract_type_permission_cannot_view_any_contract_types(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_contract_type_permission_can_view_any_contract_types(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_CONTRACT_TYPE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_read_contract_type_permission_cannot_view_contract_type(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->view($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_contract_type_permission_can_view_contract_type_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_CONTRACT_TYPE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->view($this->user, $this->contractType);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_read_contract_type_permission_cannot_view_contract_type_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_CONTRACT_TYPE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->view($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_create_contract_type_permission_cannot_create_contract_types(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_create_contract_type_permission_can_create_contract_types(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::CREATE_CONTRACT_TYPE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_update_contract_type_permission_cannot_update_contract_types(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->update($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_contract_type_permission_can_update_contract_type_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_CONTRACT_TYPE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->update($this->user, $this->contractType);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_update_contract_type_permission_cannot_update_contract_type_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_CONTRACT_TYPE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->update($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_delete_contract_type_permission_cannot_delete_contract_types(): void
    {
        // Arrange
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_contract_type_permission_can_delete_contract_type_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_CONTRACT_TYPE);
        Context::add('financer_id', $this->financer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->contractType);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_delete_contract_type_permission_cannot_delete_contract_type_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_CONTRACT_TYPE);
        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->delete($this->user, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_contract_type_permission_and_wrong_current_financer_cannot_view_contract_types(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_CONTRACT_TYPE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->view($userWithPermission, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_contract_type_permission_and_wrong_current_financer_cannot_update_contract_types(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_CONTRACT_TYPE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->update($userWithPermission, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_contract_type_permission_and_wrong_current_financer_cannot_delete_contract_types(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_CONTRACT_TYPE);

        Context::add('financer_id', $this->otherFinancer->id);

        // Act
        $result = $this->policy->delete($userWithPermission, $this->contractType);

        // Assert
        $this->assertFalse($result);
    }
}
