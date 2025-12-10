<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Policies\TagPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('tag')]
#[Group('policy')]
class TagPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private TagPolicy $policy;

    private User $user;

    private User $otherUser;

    private Tag $tag;

    private Tag $otherTag;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new TagPolicy;

        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_TAG,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_TAG,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_TAG,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_TAG,
            'guard_name' => 'api',
        ]);

        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        $this->financer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);
        $this->otherFinancer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);

        $this->tag = Tag::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en-GB' => 'Test Tag', 'fr-FR' => 'Tag Test'],
        ]);

        $this->otherTag = Tag::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en-GB' => 'Other Tag', 'fr-FR' => 'Autre Tag'],
        ]);

        $this->financer->load('division');
        $this->otherFinancer->load('division');

        $this->resetAuthorizationContext();
    }

    protected function tearDown(): void
    {
        $this->resetAuthorizationContext();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_tag_permission_cannot_view_any_tags(): void
    {
        // Arrange
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_tag_permission_can_view_any_tags(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_TAG);
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->viewAny($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_read_tag_permission_cannot_view_tag(): void
    {
        // Arrange
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->view($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_tag_permission_can_view_tag_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_TAG);
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->view($this->user, $this->tag);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_read_tag_permission_cannot_view_tag_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::READ_TAG);
        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->view($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_create_tag_permission_cannot_create_tags(): void
    {
        // Arrange
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_create_tag_permission_can_create_tags(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::CREATE_TAG);
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->create($this->user);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_without_update_tag_permission_cannot_update_tags(): void
    {
        // Arrange
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->update($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_tag_permission_can_update_tag_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_TAG);
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->update($this->user, $this->tag);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_update_tag_permission_cannot_update_tag_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::UPDATE_TAG);
        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->update($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_without_delete_tag_permission_cannot_delete_tags(): void
    {
        // Arrange
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->delete($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_tag_permission_can_delete_tag_in_same_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_TAG);
        $this->hydrateAuthorizationContext([$this->financer]);

        // Act
        $result = $this->policy->delete($this->user, $this->tag);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function user_with_delete_tag_permission_cannot_delete_tag_in_different_financer(): void
    {
        // Arrange
        $this->user->givePermissionTo(PermissionDefaults::DELETE_TAG);
        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->delete($this->user, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_read_tag_permission_and_wrong_current_financer_cannot_view_tags(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_TAG);

        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->view($userWithPermission, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_update_tag_permission_and_wrong_current_financer_cannot_update_tags(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_TAG);

        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->update($userWithPermission, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function user_with_delete_tag_permission_and_wrong_current_financer_cannot_delete_tags(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_TAG);

        $this->hydrateAuthorizationContext([$this->otherFinancer]);

        // Act
        $result = $this->policy->delete($userWithPermission, $this->tag);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * @param  array<int, Financer>  $financers
     */
    private function hydrateAuthorizationContext(array $financers): void
    {
        $financerIds = collect($financers)->pluck('id')->filter()->values()->all();
        $divisionIds = collect($financers)->pluck('division_id')->filter()->values()->all();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            $financerIds,
            $divisionIds,
            [],
            $financerIds[0] ?? null
        );
    }

    private function resetAuthorizationContext(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            [],
            null
        );
    }
}
